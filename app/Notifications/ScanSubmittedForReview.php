<?php

namespace App\Notifications;

use App\Models\File;
use App\Models\Requests;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ScanSubmittedForReview extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Requests $request,
        private readonly File $scanFile,
        private readonly string $uploaderName,
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
        $link = '/records/pending-unorganized?highlight='.rawurlencode($this->scanFile->id);

        return [
            'type' => 'scan_submitted_for_review',
            'request_id' => $this->request->id,
            'request_title' => $this->request->request_title,
            'file_id' => $this->scanFile->id,
            'file_name' => $this->scanFile->displayName(),
            'uploader' => $this->uploaderName,
            'message' => "A scan was submitted for review on request \"{$this->request->request_title}\" by {$this->uploaderName}.",
            'link' => $link,
        ];
    }
}
