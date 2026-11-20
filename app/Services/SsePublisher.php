<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class SsePublisher
{
    private const FILE_KEY_PREFIX = 'sse:file:';

    private const PENDING_KEY = 'sse:pending-unorganized';

    private const NOTIFICATION_KEY_PREFIX = 'sse:notification:user:';

    /** Publish a file-level status update and also notify the pending list. */
    public function publishFileUpdate(string $fileId, string $status): void
    {
        try {
            $payload = json_encode([
                'file_id' => $fileId,
                'status' => $status,
                'ts' => time(),
            ]);

            Redis::lpush(self::FILE_KEY_PREFIX.$fileId, $payload);
            Redis::lpush(self::PENDING_KEY, $payload);

            // Trim to avoid unbounded growth; 50 entries is ample for any client catching up.
            Redis::ltrim(self::FILE_KEY_PREFIX.$fileId, 0, 49);
            Redis::ltrim(self::PENDING_KEY, 0, 99);
        } catch (\Throwable $e) {
            Log::warning('SSE Publish failed: Redis server is offline or unreachable. ' . $e->getMessage());
        }
    }

    public static function fileKey(string $fileId): string
    {
        return self::FILE_KEY_PREFIX.$fileId;
    }

    public static function pendingKey(): string
    {
        return self::PENDING_KEY;
    }

    /**
     * Publish a user-scoped notification update payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function publishNotificationUpdate(string $userId, array $payload): void
    {
        try {
            $encoded = json_encode($payload);

            if ($encoded === false) {
                return;
            }

            $key = self::notificationKey($userId);

            Redis::lpush($key, $encoded);
            Redis::ltrim($key, 0, 99);
        } catch (\Throwable $e) {
            Log::warning('SSE Notification Publish failed: Redis server is offline or unreachable. ' . $e->getMessage());
        }
    }

    public static function notificationKey(string $userId): string
    {
        return self::NOTIFICATION_KEY_PREFIX.$userId;
    }
}
