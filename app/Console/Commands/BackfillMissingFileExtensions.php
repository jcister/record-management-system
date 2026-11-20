<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackfillMissingFileExtensions extends Command
{
    protected $signature = 'files:backfill-extensions {--dry-run : Preview changes without applying}';

    protected $description = 'Backfill missing file extensions for chunked uploads';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        // Find files with paths missing extensions
        $files = File::query()
            ->whereNotNull('file_path')
            ->whereNotNull('mime_type')
            ->get()
            ->filter(function ($file) {
                $extension = pathinfo($file->file_path, PATHINFO_EXTENSION);

                return empty($extension) && $file->mime_type !== null;
            });

        $this->info("Found {$files->count()} files with missing extensions");

        if ($files->isEmpty()) {
            $this->info('No files need backfilling.');

            return 0;
        }

        $updated = 0;
        $failed = 0;

        foreach ($files as $file) {
            $extension = $this->guessExtensionFromMime($file->mime_type);

            if ($extension === '') {
                $this->warn("No extension mapping for MIME: {$file->mime_type} (File ID: {$file->id})");
                $failed++;

                continue;
            }

            $oldPath = $file->file_path;
            $newPath = $oldPath.'.'.$extension;

            $this->line("File ID {$file->id}: {$oldPath} → {$newPath}");

            if (! $isDryRun) {
                try {
                    DB::transaction(function () use ($file, $oldPath, $newPath) {
                        $disk = config('filesystems.default', 'records');

                        // Rename physical file if it exists
                        if (Storage::disk($disk)->exists($oldPath)) {
                            Storage::disk($disk)->move($oldPath, $newPath);
                        }

                        // Update the File record
                        $file->update(['file_path' => $newPath]);

                        // Update all FileVersion records for this file
                        $file->versions()->update(['file_path' => $newPath]);
                    });
                    $updated++;
                } catch (\Exception $e) {
                    $this->error("Failed to update file {$file->id}: {$e->getMessage()}");
                    $failed++;
                }
            }
        }

        if ($isDryRun) {
            $this->info("DRY RUN: Would update {$files->count()} files");
            $this->warn("{$failed} files cannot be mapped (unknown MIME types)");
        } else {
            $this->info("Successfully updated {$updated} files");
            if ($failed > 0) {
                $this->warn("{$failed} files failed or had unknown MIME types");
            }
        }

        return 0;
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
