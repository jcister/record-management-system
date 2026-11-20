<?php

namespace App\Console\Commands;

use App\Models\DisposalBatch;
use App\Notifications\DisposalBatchScheduleReminder;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyDisposalBatchSchedule extends Command
{
    protected $signature = 'notifications:notify-disposal-batch-schedule
                            {--dedup-hours=23 : Hours to wait before re-notifying about the same batch}';

    protected $description = 'Notify staff about disposal batches scheduled for today (D-day) or in 3 days (D-3).';

    public function handle(NotificationService $notificationService): int
    {
        $dedupHours = (int) $this->option('dedup-hours');
        $today = now()->toDateString();
        $threeDaysFromNow = now()->addDays(3)->toDateString();

        $this->components->info("Checking for disposal batches scheduled on {$today} (D-day) or {$threeDaysFromNow} (D-3)...");

        $notified = 0;
        $skipped = 0;

        // D-day notifications
        DisposalBatch::query()
            ->where('status', DisposalBatch::STATUS_SCHEDULED)
            ->whereDate('scheduled_at', $today)
            ->each(function (DisposalBatch $batch) use ($notificationService, $dedupHours, &$notified, &$skipped): void {
                if ($this->wasRecentlyNotified($batch->id, 0, $dedupHours)) {
                    $skipped++;

                    return;
                }

                $notificationService->notifyDisposalBatchSchedule($batch, 0);
                $notified++;
            });

        // D-3 notifications
        DisposalBatch::query()
            ->where('status', DisposalBatch::STATUS_SCHEDULED)
            ->whereDate('scheduled_at', $threeDaysFromNow)
            ->each(function (DisposalBatch $batch) use ($notificationService, $dedupHours, &$notified, &$skipped): void {
                if ($this->wasRecentlyNotified($batch->id, 3, $dedupHours)) {
                    $skipped++;

                    return;
                }

                $notificationService->notifyDisposalBatchSchedule($batch, 3);
                $notified++;
            });

        $this->components->info("Notifications sent for {$notified} batch(es). Skipped {$skipped} (recently notified).");

        return self::SUCCESS;
    }

    private function wasRecentlyNotified(string $batchId, int $daysUntil, int $withinHours): bool
    {
        return DB::table('notifications')
            ->where('type', DisposalBatchScheduleReminder::class)
            ->where('data->batch_id', $batchId)
            ->where('data->days_until_scheduled', $daysUntil)
            ->where('created_at', '>=', now()->subHours($withinHours))
            ->exists();
    }
}
