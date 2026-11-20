<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Support\RetentionStatusCalculator;
use Illuminate\Console\Command;

class RecalculateRetentionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retention:recalculate-status {--chunk=200 : Number of records to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate the retention_status field for all files based on retention expiration.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        if ($chunkSize < 1) {
            $chunkSize = 200;
        }

        $updated = 0;
        $this->components->info('Recalculating retention statuses...');

        File::query()
            ->orderBy('id')
            ->chunk($chunkSize, function ($files) use (&$updated): void {
                foreach ($files as $file) {
                    $targetStatus = RetentionStatusCalculator::determine($file->retention_expires_at);

                    if ($file->retention_status === $targetStatus) {
                        continue;
                    }

                    $file->forceFill(['retention_status' => $targetStatus])->save();
                    $updated++;
                }
            });

        $this->components->info("Updated {$updated} file(s).");

        return self::SUCCESS;
    }
}
