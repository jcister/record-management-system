<?php

namespace App\Notifications;

use App\Models\Requests;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(private readonly Requests $request, private readonly string $requestorName) {}

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
        $link = '/requests';

        if ($requestCode !== '') {
            $link .= '?highlight='.rawurlencode($requestCode);
        }

        return [
            'type' => 'new_request',
            'request_id' => $this->request->id,
            'request_title' => $this->request->request_title,
            'requested_by' => $this->requestorName,
            'message' => "New request submitted: \"{$this->request->request_title}\" by {$this->requestorName}.",
            'link' => $link,
        ];
    }
}
