<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Notifications\RetentionDurationReached;
use App\Services\NotificationService;
use App\Support\RetentionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyRetentionExpiring extends Command
{
    protected $signature = 'notifications:notify-retention-expiring
                            {--dedup-hours=23 : Hours to wait before re-notifying about the same record}';

    protected $description = 'Notify staff about records whose retention duration expires today.';

    public function handle(NotificationService $notificationService): int
    {
        $dedupHours = (int) $this->option('dedup-hours');
        $today = now()->toDateString();

        $this->components->info("Checking for records with retention expiring on {$today}...");

        $notified = 0;
        $skipped = 0;

        File::query()
            ->where('is_record', true)
            ->whereNotNull('retention_expires_at')
            ->where('retention_status', RetentionStatus::ACTIVE)
            ->whereDate('retention_expires_at', $today)
            ->orderBy('retention_expires_at')
            ->each(function (File $file) use ($notificationService, $dedupHours, &$notified, &$skipped): void {
                if ($this->wasRecentlyNotified($file->id, $dedupHours)) {
                    $skipped++;

                    return;
                }

                $notificationService->notifyRetentionDurationReached($file);
                $notified++;
            });

        $this->components->info("Notifications sent for {$notified} record(s). Skipped {$skipped} (recently notified).");

        return self::SUCCESS;
    }

    private function wasRecentlyNotified(string $fileId, int $withinHours): bool
    {
        return DB::table('notifications')
            ->where('type', RetentionDurationReached::class)
            ->where('data->file_id', $fileId)
            ->where('created_at', '>=', now()->subHours($withinHours))
            ->exists();
    }
}
