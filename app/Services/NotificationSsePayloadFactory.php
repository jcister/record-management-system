<?php

namespace App\Services;

use Illuminate\Notifications\DatabaseNotification;

class NotificationSsePayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function created(DatabaseNotification $notification, int $unreadCount): array
    {
        return [
            'event' => 'notification_created',
            'notification' => $this->serializeNotification($notification),
            'unread_count' => $unreadCount,
            'ts' => time(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $notificationId, string $readAt, int $unreadCount): array
    {
        return [
            'event' => 'notification_read',
            'notification_id' => $notificationId,
            'read_at' => $readAt,
            'unread_count' => $unreadCount,
            'ts' => time(),
        ];
    }

    /**
     * @param  array<int, string>  $notificationIds
     * @return array<string, mixed>
     */
    public function allRead(array $notificationIds, string $readAt, int $unreadCount): array
    {
        return [
            'event' => 'notifications_all_read',
            'notification_ids' => $notificationIds,
            'read_at' => $readAt,
            'unread_count' => $unreadCount,
            'ts' => time(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeNotification(DatabaseNotification $notification): array
    {
        return [
            'id' => (string) $notification->id,
            'type' => (string) $notification->type,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toJSON(),
            'created_at' => $notification->created_at?->toJSON(),
        ];
    }
}
