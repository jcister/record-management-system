<?php

namespace App\Notifications;

use App\Models\File;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RecordDisposalApproaching extends Notification
{
    use Queueable;

    public readonly string $urgency;

    public readonly int $daysUntilExpiry;

    public function __construct(private readonly File $file)
    {
        $expiresAt = CarbonImmutable::parse($this->file->retention_expires_at);
        $this->daysUntilExpiry = (int) now()->startOfDay()->diffInDays($expiresAt->startOfDay(), false);
        $this->urgency = $this->daysUntilExpiry <= 7 ? 'critical' : 'warning';
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
        $name = $this->file->displayName();
        $message = $this->daysUntilExpiry <= 0
            ? "Record \"{$name}\" is overdue for disposal."
            : "Record \"{$name}\" is due for disposal in {$this->daysUntilExpiry} day(s).";

        return [
            'type' => 'record_disposal_approaching',
            'file_id' => $this->file->id,
            'record_id' => $this->file->record_id,
            'file_name' => $name,
            'days_until_expiry' => $this->daysUntilExpiry,
            'urgency' => $this->urgency,
            'message' => $message,
            'link' => '/records',
        ];
    }
}
