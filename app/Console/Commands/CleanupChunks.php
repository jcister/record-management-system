<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CleanupChunks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:chunks {--hours=24 : Number of hours after which to clean up chunks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned chunk uploads older than the specified hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoffTime = now()->subHours($hours);

        $this->info("Cleaning up chunks older than {$hours} hours (before {$cutoffTime->toDateTimeString()})...");

        $disk = Storage::disk('chunks');
        $directories = $disk->directories('/');
        $deletedCount = 0;
        $errorCount = 0;

        foreach ($directories as $directory) {
            try {
                $lastModified = $disk->lastModified($directory);
                $lastModifiedTime = \Carbon\Carbon::createFromTimestamp($lastModified);

                if ($lastModifiedTime->isBefore($cutoffTime)) {
                    $uploadSessionId = basename($directory);

                    $disk->deleteDirectory($directory);

                    Cache::forget("chunk_upload:{$uploadSessionId}");

                    $deletedCount++;
                    $this->info("Deleted: {$directory} (last modified: {$lastModifiedTime->toDateTimeString()})");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("Failed to delete {$directory}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Cleanup complete!');
        $this->info("Deleted: {$deletedCount} chunk directories");

        if ($errorCount > 0) {
            $this->warn("Errors: {$errorCount} directories failed to delete");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
