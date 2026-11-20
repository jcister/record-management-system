<?php

namespace App\Notifications;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RetentionDurationReached extends Notification
{
    use Queueable;

    public function __construct(private readonly File $file) {}

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

        return [
            'type' => 'retention_duration_reached',
            'file_id' => $this->file->id,
            'record_id' => $this->file->record_id,
            'file_name' => $name,
            'urgency' => 'action_required',
            'message' => "Record \"{$name}\" has reached its retention duration and requires action.",
            'link' => '/records',
        ];
    }
}
