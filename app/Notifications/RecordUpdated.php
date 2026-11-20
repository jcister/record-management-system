<?php

namespace App\Notifications;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RecordUpdated extends Notification
{
    use Queueable;

    public function __construct(private readonly File $file, private readonly string $actorName) {}

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
        return [
            'type' => 'record_updated',
            'file_id' => $this->file->id,
            'record_id' => $this->file->record_id,
            'file_name' => $this->file->displayName(),
            'actor' => $this->actorName,
            'message' => "Record \"{$this->file->displayName()}\" was updated by {$this->actorName}.",
            'link' => '/records',
        ];
    }
}
