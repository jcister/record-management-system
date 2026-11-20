<?php

namespace App\Notifications;

use App\Models\Requests;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ScanRejected extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Requests $request,
        private readonly string $rejectorName,
        private readonly string $reason,
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
        return [
            'type' => 'scan_rejected',
            'request_id' => $this->request->id,
            'request_title' => $this->request->request_title,
            'rejector' => $this->rejectorName,
            'reason' => $this->reason,
            'message' => "Your scan for request \"{$this->request->request_title}\" was rejected by {$this->rejectorName}. Reason: {$this->reason}",
            'link' => $this->link,
        ];
    }
}
