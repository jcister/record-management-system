<?php

namespace App\Listeners\Notifications;

use App\Models\User;
use App\Services\NotificationSsePayloadFactory;
use App\Services\SsePublisher;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

class PublishNotificationSseUpdate
{
    public function __construct(
        private readonly SsePublisher $ssePublisher,
        private readonly NotificationSsePayloadFactory $payloadFactory
    ) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (! $event->notifiable instanceof User) {
            return;
        }

        if (! $event->response instanceof DatabaseNotification) {
            return;
        }

        $payload = $this->payloadFactory->created(
            $event->response,
            $event->notifiable->unreadNotifications()->count()
        );

        $this->ssePublisher->publishNotificationUpdate((string) $event->notifiable->id, $payload);
    }
}
