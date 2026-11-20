<?php

namespace App\Notifications;

use App\Models\DisposalBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DisposalBatchScheduleReminder extends Notification
{
    use Queueable;

    public readonly string $urgency;

    public function __construct(
        private readonly DisposalBatch $batch,
        public readonly int $daysUntilScheduled,
    ) {
        $this->urgency = $this->daysUntilScheduled === 0 ? 'today' : 'upcoming';
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $reference = $this->batch->reference ?? $this->batch->id;
        $fileCount = $this->batch->file_count ?? 0;

        $message = $this->daysUntilScheduled === 0
            ? "Disposal batch \"{$reference}\" ({$fileCount} files) is scheduled for TODAY."
            : "Disposal batch \"{$reference}\" ({$fileCount} files) is scheduled in {$this->daysUntilScheduled} day(s).";

        return [
            'type' => 'disposal_batch_schedule_reminder',
            'batch_id' => $this->batch->id,
            'reference' => $reference,
            'file_count' => $fileCount,
            'days_until_scheduled' => $this->daysUntilScheduled,
            'urgency' => $this->urgency,
            'message' => $message,
            'link' => '/disposal',
        ];
    }
}
