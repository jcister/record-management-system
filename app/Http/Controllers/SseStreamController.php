<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\SsePublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseStreamController extends Controller
{
    /**
     * Stream OCR/assembly status updates for a single file.
     *
     * Used by FileView.vue while the file is in a processing state.
     * The client connects once; the server blocks on BRPOP and pushes a
     * message each time the pipeline publishes a state change.
     */
    public function fileUpdates(Request $request, File $file): StreamedResponse
    {
        $key = SsePublisher::fileKey($file->id);

        return $this->stream($key);
    }

    /**
     * Stream status updates for the Pending & Unorganized page.
     *
     * Any assembly/OCR transition for any unorganised file triggers a reload.
     */
    public function pendingUpdates(Request $request): StreamedResponse
    {
        $key = SsePublisher::pendingKey();

        return $this->stream($key);
    }

    /**
     * Stream notification updates for the authenticated user.
     */
    public function notificationUpdates(Request $request): StreamedResponse
    {
        $user = $request->user();
        $key = SsePublisher::notificationKey((string) $user->id);

        return $this->stream($key, drainBacklogBeforeWait: true);
    }

    /**
     * Core SSE streaming loop backed by a Redis list.
     *
     * - Sends an immediate comment prelude so response headers and the first
     *   chunk reach the client before the first blocking BRPOP (avoids long
     *   time-to-first-byte when the queue is empty).
     * - BRPOP uses a short timeout so we re-check `connection_aborted()` often.
     *   This avoids holding single-threaded dev servers (e.g. `php artisan serve`)
     *   for tens of seconds after the client closes the stream. Heartbeats still
     *   satisfy typical proxy idle limits.
     * - After enough consecutive idle heartbeats, the stream closes so the
     *   client EventSource reconnects.
     * - Last-Event-ID / replay semantics are not implemented; the optional
     *   backlog drain for notifications only delivers messages still present in Redis.
     */
    private function stream(string $redisKey, bool $drainBacklogBeforeWait = false): StreamedResponse
    {
        return response()->stream(
            function () use ($redisKey, $drainBacklogBeforeWait): void {
                // Disable output buffering so chunks reach the browser.
                if (ob_get_level()) {
                    ob_end_flush();
                }

                $idle = 0;
                $maxIdleSeconds = 5 * 60; // 5 minutes
                $brpopTimeout = 5; // seconds — low so single-worker dev releases soon after disconnect

                echo ": connected\n\n";
                $this->flushSseOutput();

                if ($drainBacklogBeforeWait) {
                    $this->drainAndEmitSseBacklog($redisKey);
                }

                while (true) {
                    if (connection_aborted()) {
                        break;
                    }

                    $result = Redis::brpop($redisKey, $brpopTimeout);

                    if ($result !== null) {
                        // $result is [key, value]
                        $data = $result[1];
                        echo "data: {$data}\n\n";
                        $idle = 0;
                    } else {
                        // Heartbeat comment — keeps the connection alive.
                        echo ": ping\n\n";
                        $idle += $brpopTimeout;
                    }

                    $this->flushSseOutput();

                    if ($idle >= $maxIdleSeconds) {
                        break;
                    }
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no', // disable Nginx proxy buffering
                'Connection' => 'keep-alive',
            ]
        );
    }

    /**
     * Non-blocking drain of pending Redis list entries (same end as BRPOP)
     * so reconnecting notification clients receive backlog without waiting for BRPOP.
     */
    private function drainAndEmitSseBacklog(string $redisKey): void
    {
        while (true) {
            $payload = Redis::rpop($redisKey);

            if ($payload === null || $payload === false) {
                break;
            }

            echo "data: {$payload}\n\n";
            $this->flushSseOutput();
        }
    }

    private function flushSseOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
