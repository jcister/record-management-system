<?php

namespace App\Notifications;

use App\Models\Requests;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequestStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Requests $request,
        private readonly string $oldStatus,
        private readonly string $newStatus,
        private readonly string $actorName,
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
        $requestCode = (string) ($this->request->request_id ?? '');
        $link = '/requests/myrequests';

        if ($requestCode !== '') {
            $link .= '?highlight='.rawurlencode($requestCode);
        }

        return [
            'type' => 'request_status_updated',
            'request_id' => $this->request->id,
            'request_title' => $this->request->request_title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'actor' => $this->actorName,
            'message' => "Your request \"{$this->request->request_title}\" status was updated to {$this->newStatus} by {$this->actorName}.",
            'link' => $link,
        ];
    }
}
