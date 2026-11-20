<?php

namespace App\Notifications;

use App\Models\Requests;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequestWillReturnCopyChanged extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Requests $request,
        private readonly bool $oldValue,
        private readonly bool $newValue,
        private readonly string $changedBy,
        private readonly string $link,
    ) {}

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
        $oldText = $this->oldValue ? 'Yes' : 'No';
        $newText = $this->newValue ? 'Yes' : 'No';

        return [
            'type' => 'request_will_return_copy_changed',
            'request_id' => $this->request->id,
            'request_title' => $this->request->request_title,
            'old_value' => $oldText,
            'new_value' => $newText,
            'changed_by' => $this->changedBy,
            'message' => "Request \"{$this->request->request_title}\" - \"Will Return Updated Copy\" changed from {$oldText} to {$newText} by {$this->changedBy}.",
            'link' => $this->link,
        ];
    }
}
