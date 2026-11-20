<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\AuditService;
use App\Services\FileChunkHandler;
use App\Services\FileVersionService;
use App\Services\SsePublisher;
use App\Support\FileName;
use App\Support\RecordTypeHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AssembleChunkedFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public string $uploadSessionId,
        public string $fileId,
    ) {}

    public function handle(FileChunkHandler $chunkHandler, FileVersionService $fileVersionService, AuditService $audit, SsePublisher $sse): void
    {
        $sessionData = Cache::get("chunk_upload:{$this->uploadSessionId}");

        if (! $sessionData) {
            Log::error('Upload session not found', [
                'upload_session_id' => $this->uploadSessionId,
            ]);
            $this->fail(new \Exception('Upload session not found'));

            return;
        }

        // The File record was pre-created in ChunkedUploadController@complete.
        $file = File::query()->findOrFail($this->fileId);

        try {
            if (! $chunkHandler->validateChunks($this->uploadSessionId, $sessionData['total_chunks'])) {
                throw new \Exception('Chunk validation failed: Missing or incomplete chunks');
            }

            $displayName = FileName::normalize($sessionData['file_name'] ?? '') ?? '';
            $recordName = $displayName !== '' ? $displayName : 'file';

            // Extract original extension from session with MIME fallback
            $extension = $this->resolveExtension(
                $sessionData['original_extension'] ?? '',
                $recordName
            );

            $baseName = $extension ? pathinfo($recordName, PATHINFO_FILENAME) : $recordName;
            $destinationPath = 'private/uploads/'.uniqid().'_'.$baseName.($extension ? '.'.$extension : '');

            $assembled = $chunkHandler->assembleChunks(
                $this->uploadSessionId,
                $destinationPath,
                config('filesystems.default', 'records')
            );

            if (! $assembled) {
                throw new \Exception('Failed to assemble chunks into final file');
            }

            $disk = config('filesystems.default', 'records');
            $fullPath = Storage::disk($disk)->path($destinationPath);

            if (! file_exists($fullPath)) {
                throw new \Exception('Assembled file does not exist on disk');
            }

            $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';
            $fileSize = filesize($fullPath);

            // Update extension using MIME type if we couldn't get it earlier
            if ($extension === '') {
                $extension = strtolower($this->guessExtensionFromMime($mimeType));

                // If we got an extension from MIME, update the destination path
                if ($extension !== '') {
                    $baseName = $extension ? pathinfo($recordName, PATHINFO_FILENAME) : $recordName;
                    if ($baseName === '') {
                        $baseName = $recordName;
                    }
                    $newDestinationPath = 'private/uploads/'.uniqid().'_'.$baseName.'.'.$extension;

                    // Rename the assembled file
                    $disk = config('filesystems.default', 'records');
                    Storage::disk($disk)->move($destinationPath, $newDestinationPath);
                    $destinationPath = $newDestinationPath;
                    $fullPath = Storage::disk($disk)->path($destinationPath);
                }
            } else {
                $extension = strtolower($extension);
            }

            if (! RecordTypeHelper::isRecordType($mimeType, $extension)) {
                // Attachment (not a PDF/image record). Flip the pre-created file
                // to a non-record and mark it completed — no OCR needed.
                $file->fill([
                    'is_record' => false,
                    'record_id' => 'ATTACH-'.strtoupper(uniqid()),
                ])->save();

                $fileVersionService->createVersion(
                    $file,
                    [
                        'file_path' => $destinationPath,
                        'file_name' => $recordName,
                        'mime_type' => $mimeType,
                        'file_size' => $fileSize,
                        'uploaded_by' => $sessionData['user_id'],
                        'assembly_status' => 'completed',
                        'ocr_status' => 'completed',
                    ],
                    $disk
                );

                $chunkHandler->cleanupChunks($this->uploadSessionId);
                Cache::forget("chunk_upload:{$this->uploadSessionId}");

                $audit->log(
                    'chunked_upload_assembled_stored_only',
                    'Chunked upload assembled (stored only, no record) for file "'.$recordName.'".',
                    $file,
                    [
                        'upload_session_id' => $this->uploadSessionId,
                        'file_id' => $file->id,
                        'file_size' => $fileSize,
                        'total_chunks' => $sessionData['total_chunks'],
                        'user_id' => $sessionData['user_id'],
                    ],
                    null,
                    AuditService::SYSTEM_ACTOR_NAME
                );

                Log::info('Chunked file assembled as stored file only', [
                    'upload_session_id' => $this->uploadSessionId,
                    'file_id' => $file->id,
                    'file_size' => $fileSize,
                ]);

                $sse->publishFileUpdate($file->id, 'assembly_completed');

                return;
            }

            // Record type (PDF / image). createVersion updates the File row
            // (file_path, mime_type, assembly_status, ocr_status) via forceFill.
            $fileVersion = $fileVersionService->createVersion(
                $file->fresh(),
                [
                    'file_path' => $destinationPath,
                    'file_name' => $recordName,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'uploaded_by' => $sessionData['user_id'],
                    'assembly_status' => 'completed',
                    'ocr_status' => 'pending',
                    'ocr_language' => $file->ocr_language,
                ],
                $disk
            );

            ProcessFileOcr::dispatch($fileVersion->id);

            $chunkHandler->cleanupChunks($this->uploadSessionId);
            Cache::forget("chunk_upload:{$this->uploadSessionId}");

            $audit->log(
                'chunked_upload_assembled',
                'Chunked upload assembled successfully for file "'.$file->file_name.'".',
                $file,
                [
                    'upload_session_id' => $this->uploadSessionId,
                    'file_size' => $fileSize,
                    'total_chunks' => $sessionData['total_chunks'],
                    'user_id' => $sessionData['user_id'],
                ],
                null,
                AuditService::SYSTEM_ACTOR_NAME
            );

            Log::info('Chunked file assembled successfully', [
                'upload_session_id' => $this->uploadSessionId,
                'file_id' => $file->id,
                'file_size' => $fileSize,
            ]);

            $sse->publishFileUpdate($file->id, 'assembly_completed');
        } catch (\Exception $e) {
            $audit->log(
                'chunked_upload_failed',
                'Chunked upload assembly failed for session "'.$this->uploadSessionId.'".',
                $file,
                [
                    'upload_session_id' => $this->uploadSessionId,
                    'error' => $e->getMessage(),
                    'user_id' => $sessionData['user_id'] ?? null,
                ],
                null,
                AuditService::SYSTEM_ACTOR_NAME
            );

            Log::error('Failed to assemble chunked file', [
                'upload_session_id' => $this->uploadSessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AssembleChunkedFile job failed permanently', [
            'upload_session_id' => $this->uploadSessionId,
            'file_id' => $this->fileId,
            'error' => $exception->getMessage(),
        ]);

        // Mark the pre-created File as failed so the UI can reflect this.
        File::query()->find($this->fileId)?->fill(['assembly_status' => 'failed'])->save();

        $chunkHandler = app(FileChunkHandler::class);
        $chunkHandler->cleanupChunks($this->uploadSessionId);
        Cache::forget("chunk_upload:{$this->uploadSessionId}");

        app(SsePublisher::class)->publishFileUpdate($this->fileId, 'assembly_failed');
    }

    private function resolveExtension(string $originalExtension, string $fileName): string
    {
        // 1. Use original extension from upload session (most reliable)
        if ($originalExtension !== '') {
            return strtolower($originalExtension);
        }

        // 2. Try extracting from filename (fallback, shouldn't happen).
        // Validate that the extracted segment is a real extension (alphanumeric,
        // contains at least one letter, max 8 chars). This rejects dotted numeric
        // suffixes like "-2025" that pathinfo() would otherwise return.
        $fileExtension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileExtension !== '' && preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]{1,8}$/', $fileExtension)) {
            return $fileExtension;
        }

        // 3. Can't resolve without MIME type - will be empty (MIME fallback happens later)
        return '';
    }

    private function guessExtensionFromMime(?string $mimeType): string
    {
        $mime = strtolower((string) $mimeType);

        return match ($mime) {
            'application/pdf', 'application/x-pdf' => 'pdf',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/tiff' => 'tiff',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
            'image/heif', 'image/heic' => 'heic',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => '',
        };
    }
}
