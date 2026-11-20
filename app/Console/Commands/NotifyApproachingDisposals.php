<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Notifications\RecordDisposalApproaching;
use App\Services\NotificationService;
use App\Support\RetentionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyApproachingDisposals extends Command
{
    protected $signature = 'notifications:notify-approaching-disposals
                            {--days=30 : Number of days ahead to check for approaching disposals}
                            {--dedup-hours=23 : Hours to wait before re-notifying about the same record}';

    protected $description = 'Notify staff about records with disposal dates approaching or already overdue.';

    public function handle(NotificationService $notificationService): int
    {
        $days = (int) $this->option('days');
        $dedupHours = (int) $this->option('dedup-hours');

        $this->components->info("Checking for records with disposal dates within {$days} days...");

        $notified = 0;
        $skipped = 0;

        File::query()
            ->where('is_record', true)
            ->whereNotNull('retention_expires_at')
            ->where('retention_status', RetentionStatus::ACTIVE)
            ->where('retention_expires_at', '<=', now()->addDays($days)->toDateString())
            ->orderBy('retention_expires_at')
            ->each(function (File $file) use ($notificationService, $dedupHours, &$notified, &$skipped): void {
                if ($this->wasRecentlyNotified($file->id, $dedupHours)) {
                    $skipped++;

                    return;
                }

                $notificationService->notifyApproachingDisposals($file);
                $notified++;
            });

        $this->components->info("Notifications sent for {$notified} record(s). Skipped {$skipped} (recently notified).");

        return self::SUCCESS;
    }

    private function wasRecentlyNotified(string $fileId, int $withinHours): bool
    {
        return DB::table('notifications')
            ->where('type', RecordDisposalApproaching::class)
            ->where('data->file_id', $fileId)
            ->where('created_at', '>=', now()->subHours($withinHours))
            ->exists();
    }
}
