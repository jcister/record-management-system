<?php

namespace App\Http\Controllers;

use App\Models\FileVersion;
use App\Services\AuditService;
use App\Services\SsePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives OCR completion callbacks from an external OCR microservice.
 *
 * Every request must carry an X-OCR-Signature header containing the
 * HMAC-SHA256 hex digest of the raw request body, signed with the
 * shared secret stored in OCR_WEBHOOK_SECRET.
 *
 * Example payload:
 * {
 *   "file_version_id": "<uuid>",
 *   "ocr_status": "completed",   // or "failed"
 *   "ocr_text": "...",           // present when completed
 *   "ocr_error": null            // present when failed
 * }
 */
class OcrCallbackController extends Controller
{
    public function __invoke(Request $request, AuditService $audit, SsePublisher $sse): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $validated = $request->validate([
            'file_version_id' => 'required|uuid|exists:file_versions,id',
            'ocr_status' => 'required|in:completed,failed',
            'ocr_text' => 'nullable|string',
            'ocr_error' => 'nullable|string|max:500',
        ]);

        $fileVersion = FileVersion::query()
            ->with('file')
            ->findOrFail($validated['file_version_id']);

        $attributes = [
            'ocr_status' => $validated['ocr_status'],
            'ocr_text' => $validated['ocr_text'] ?? null,
            'ocr_error' => isset($validated['ocr_error'])
                ? substr($validated['ocr_error'], 0, 500)
                : null,
        ];

        $fileVersion->fill($attributes)->save();

        $file = $fileVersion->file;

        if ($file !== null && $file->current_version_id === $fileVersion->id) {
            $file->fill($attributes)->save();
        }

        $audit->log(
            $validated['ocr_status'] === 'completed' ? 'file_ocr_completed' : 'file_ocr_failed',
            'OCR '.($validated['ocr_status'] === 'completed' ? 'completed' : 'failed').' via webhook.',
            $file,
            [
                'file_version_id' => $fileVersion->id,
                'text_length' => strlen($validated['ocr_text'] ?? ''),
            ],
            null,
            AuditService::SYSTEM_ACTOR_NAME
        );

        $sse->publishFileUpdate((string) $fileVersion->file_id, $validated['ocr_status'] === 'completed' ? 'ocr_completed' : 'ocr_failed');

        return response()->json(['message' => 'OCR result recorded.']);
    }

    private function signatureIsValid(Request $request): bool
    {
        $secret = config('services.ocr.webhook_secret');

        if (! $secret) {
            return false;
        }

        $signature = (string) $request->header('X-OCR-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
