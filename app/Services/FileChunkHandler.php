<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileChunkHandler
{
    protected string $disk = 'chunks';

    public function storeChunk(string $uploadSessionId, int $chunkIndex, UploadedFile $file): string
    {
        $chunkPath = "{$uploadSessionId}/chunk_{$chunkIndex}";

        $path = $file->storeAs(
            $uploadSessionId,
            "chunk_{$chunkIndex}",
            $this->disk
        );

        return $path;
    }

    public function getAllChunks(string $uploadSessionId): array
    {
        $files = Storage::disk($this->disk)->files($uploadSessionId);

        usort($files, function ($a, $b) {
            preg_match('/chunk_(\d+)$/', $a, $matchA);
            preg_match('/chunk_(\d+)$/', $b, $matchB);

            return (int) ($matchA[1] ?? 0) <=> (int) ($matchB[1] ?? 0);
        });

        return $files;
    }

    public function assembleChunks(string $uploadSessionId, string $destinationPath, string $destinationDisk = 'records'): bool
    {
        $chunks = $this->getAllChunks($uploadSessionId);

        if (empty($chunks)) {
            return false;
        }

        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        try {
            foreach ($chunks as $chunkPath) {
                $chunkContent = Storage::disk($this->disk)->get($chunkPath);
                fwrite($tempFile, $chunkContent);
            }

            fseek($tempFile, 0);

            $uploaded = Storage::disk($destinationDisk)->put(
                $destinationPath,
                $tempFile
            );

            return $uploaded;
        } finally {
            if (is_resource($tempFile)) {
                fclose($tempFile);
            }
        }
    }

    public function cleanupChunks(string $uploadSessionId): bool
    {
        return Storage::disk($this->disk)->deleteDirectory($uploadSessionId);
    }

    public function chunkExists(string $uploadSessionId, int $chunkIndex): bool
    {
        return Storage::disk($this->disk)->exists("{$uploadSessionId}/chunk_{$chunkIndex}");
    }

    public function getChunkCount(string $uploadSessionId): int
    {
        return count($this->getAllChunks($uploadSessionId));
    }

    public function validateChunks(string $uploadSessionId, int $expectedCount): bool
    {
        $chunks = $this->getAllChunks($uploadSessionId);

        if (count($chunks) !== $expectedCount) {
            return false;
        }

        for ($i = 0; $i < $expectedCount; $i++) {
            if (! $this->chunkExists($uploadSessionId, $i)) {
                return false;
            }
        }

        return true;
    }
}
