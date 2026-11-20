<?php

namespace App\Services;

use App\Jobs\GenerateFileThumbnail;
use App\Models\File;
use App\Models\FileVersion;
use App\Support\FileName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileVersionService
{
    public function createVersion(File $file, array $attributes, ?string $disk = null): FileVersion
    {
        $disk = $disk ?? config('filesystems.default', 'local');

        $version = DB::transaction(function () use ($file, $attributes, $disk): FileVersion {
            $latestNumber = (int) ($file->latest_version_number ?? 0);
            $versionNumber = $latestNumber + 1;
            $filePath = $attributes['file_path'];
            $fileName = FileName::normalize($attributes['file_name'] ?? $file->file_name) ?? 'file';
            $uploadedBy = $attributes['uploaded_by'] ?? $file->uploaded_by;
            $versionLabel = $attributes['version_label']
                ?? 'v'.$versionNumber;
            $ocrLanguage = $attributes['ocr_language']
                ?? $file->ocr_language
                ?? 'eng+fil';

            $checksum = $this->calculateChecksum($disk, $filePath);

            $version = FileVersion::query()->create([
                'file_id' => $file->id,
                'version_number' => $versionNumber,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'mime_type' => $attributes['mime_type'] ?? $file->mime_type,
                'file_size' => $attributes['file_size'] ?? $file->file_size,
                'uploaded_by' => $uploadedBy,
                'checksum' => $checksum,
                'version_label' => $versionLabel,
                'change_summary' => $attributes['change_summary'] ?? null,
                'ocr_status' => $attributes['ocr_status'] ?? 'pending',
                'ocr_text' => $attributes['ocr_text'] ?? null,
                'ocr_language' => $ocrLanguage,
                'ocr_error' => $attributes['ocr_error'] ?? null,
                'assembly_status' => $attributes['assembly_status'] ?? null,
            ]);

            $file->forceFill([
                'file_path' => $filePath,
                'file_name' => $fileName,
                'mime_type' => $version->mime_type,
                'file_size' => $version->file_size,
                'current_version_id' => $version->id,
                'latest_version_number' => $versionNumber,
                'assembly_status' => $version->assembly_status,
                'ocr_status' => $version->ocr_status,
                'ocr_text' => $version->ocr_text,
                'ocr_language' => $version->ocr_language ?? $ocrLanguage,
                'ocr_error' => $version->ocr_error,
            ])->save();

            return $version;
        });

        if ($this->shouldGenerateThumbnail($version)) {
            GenerateFileThumbnail::dispatch($version->id);
        }

        return $version;
    }

    public function promoteExistingVersion(FileVersion $sourceVersion, int|string|null $actorId = null, ?string $changeSummary = null): FileVersion
    {
        return DB::transaction(function () use ($sourceVersion, $changeSummary): FileVersion {
            $file = $sourceVersion->file()->lockForUpdate()->first();

            if ($file === null) {
                throw new \RuntimeException('Unable to restore version without an associated file.');
            }

            $file->forceFill([
                'file_path' => $sourceVersion->file_path,
                'file_name' => $sourceVersion->file_name,
                'mime_type' => $sourceVersion->mime_type,
                'file_size' => $sourceVersion->file_size,
                'current_version_id' => $sourceVersion->id,
                'assembly_status' => $sourceVersion->assembly_status,
                'ocr_status' => $sourceVersion->ocr_status,
                'ocr_text' => $sourceVersion->ocr_text,
                'ocr_language' => $sourceVersion->ocr_language,
                'ocr_error' => $sourceVersion->ocr_error,
            ])->save();

            if ($changeSummary !== null && trim($changeSummary) !== '') {
                $sourceVersion->change_summary = $changeSummary;
                $sourceVersion->save();
            }

            return $sourceVersion->fresh('uploader');
        });
    }

    private function calculateChecksum(string $disk, string $path): ?string
    {
        $absolutePath = Storage::disk($disk)->path($path);

        return is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null;
    }

    private function shouldGenerateThumbnail(FileVersion $version): bool
    {
        $mime = strtolower((string) ($version->mime_type ?? ''));
        $filename = strtolower((string) ($version->file_name ?? ''));

        return str_contains($mime, 'pdf') || str_ends_with($filename, '.pdf');
    }
}
