<?php

namespace App\Console\Commands;

use App\Jobs\ProcessFileOcr;
use App\Models\FileVersion;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RecoverStuckOcr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ocr:recover-stuck
        {--minutes=30 : Minimum minutes in processing state before recovery}
        {--max-retries=3 : Maximum automatic OCR recovery attempts per version}
        {--chunk=200 : Number of versions to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover OCR versions stuck in processing by re-queueing with bounded retries.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $maxRetries = max(1, (int) $this->option('max-retries'));
        $chunkSize = max(1, (int) $this->option('chunk'));
        $staleBefore = now()->subMinutes($minutes);

        $requeued = 0;
        $markedFailed = 0;
        $skipped = 0;

        FileVersion::query()
            ->with('file')
            ->where('ocr_status', 'processing')
            ->where('updated_at', '<=', $staleBefore)
            ->orderBy('id')
            ->chunk($chunkSize, function (Collection $versions) use (&$requeued, &$markedFailed, &$skipped, $maxRetries): void {
                foreach ($versions as $version) {
                    $file = $version->file;

                    if ($file === null) {
                        $skipped++;

                        continue;
                    }

                    $nextRetryCount = (int) $version->ocr_retry_count + 1;

                    if ($nextRetryCount > $maxRetries) {
                        $attributes = [
                            'ocr_status' => 'failed',
                            'ocr_error' => "OCR recovery retries exhausted after {$maxRetries} attempts.",
                        ];

                        $version->fill($attributes)->save();

                        if ($file->current_version_id === $version->id) {
                            $file->fill($attributes)->save();
                        }

                        $markedFailed++;

                        continue;
                    }

                    $attributes = [
                        'ocr_status' => 'pending',
                        'ocr_retry_count' => $nextRetryCount,
                        'ocr_error' => "OCR recovery attempt {$nextRetryCount}/{$maxRetries} scheduled after stale processing timeout.",
                    ];

                    $version->fill($attributes)->save();

                    if ($file->current_version_id === $version->id) {
                        $file->fill($attributes)->save();
                    }

                    ProcessFileOcr::dispatch((string) $version->id);
                    $requeued++;
                }
            });

        $this->components->info("OCR recovery complete. Requeued: {$requeued}, marked failed: {$markedFailed}, skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
