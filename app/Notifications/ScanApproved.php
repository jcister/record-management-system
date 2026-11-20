<?php

namespace App\Notifications;

use App\Models\File;
use App\Models\Requests;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ScanApproved extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Requests $request,
        private readonly File $targetFile,
        private readonly string $approverName,
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
            'type' => 'scan_approved',
            'request_id' => $this->request->id,
            'request_title' => $this->request->request_title,
            'file_id' => $this->targetFile->id,
            'record_id' => $this->targetFile->record_id,
            'approver' => $this->approverName,
            'message' => "Your scan for request \"{$this->request->request_title}\" was approved by {$this->approverName}.",
            'link' => $this->link,
        ];
    }
}
