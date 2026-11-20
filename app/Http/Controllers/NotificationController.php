<?php

namespace App\Http\Controllers;

use App\Services\NotificationSsePayloadFactory;
use App\Services\SsePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly SsePublisher $ssePublisher,
        private readonly NotificationSsePayloadFactory $payloadFactory
    ) {}

    /**
     * Count notifications per `data.type` in one query (avoids loading every row for tabs).
     *
     * @return array<string, int>
     */
    private function notificationTypeCountsForUser(Request $request): array
    {
        $user = $request->user();
        $driver = (string) $user->notifications()->getQuery()->getConnection()->getConfig('driver');

        $groupExpr = match ($driver) {
            'mysql', 'mariadb' => "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.type')), 'unknown')",
            'pgsql' => "COALESCE(data->>'type', 'unknown')",
            default => "COALESCE(json_extract(data, '$.type'), 'unknown')",
        };

        $counts = $user->notifications()
            ->toBase()
            ->reorder()
            ->selectRaw("{$groupExpr} as notification_type")
            ->selectRaw('count(*) as aggregate')
            ->groupByRaw($groupExpr)
            ->pluck('aggregate', 'notification_type');

        return $counts->map(fn (mixed $count): int => (int) $count)->all();
    }

    public function settingsPage(Request $request): Response
    {
        $user = $request->user();
        $query = $user->notifications()->latest();

        // Apply filters
        if ($request->filled('type')) {
            $typeFilter = $request->input('type');
            $types = explode(',', $typeFilter);

            $query->where(function ($q) use ($types): void {
                foreach ($types as $type) {
                    $q->orWhere('data->type', trim($type));
                }
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($status === 'unread') {
                $query->whereNull('read_at');
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('data->message', 'like', "%{$search}%")
                    ->orWhere('data->request_title', 'like', "%{$search}%")
                    ->orWhere('data->file_name', 'like', "%{$search}%")
                    ->orWhere('data->actor', 'like', "%{$search}%");
            });
        }

        $notifications = $query->paginate(20)->withQueryString();

        $typeCounts = $this->notificationTypeCountsForUser($request);

        return Inertia::render('settings/Notifications', [
            'notifications' => $notifications,
            'filters' => [
                'type' => $request->input('type', ''),
                'status' => $request->input('status', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
                'search' => $request->input('search', ''),
            ],
            'typeCounts' => $typeCounts,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function page(Request $request): Response
    {
        $user = $request->user();
        $query = $user->notifications()->latest();

        // Apply filters
        if ($request->filled('type')) {
            $typeFilter = $request->input('type');
            $types = explode(',', $typeFilter);

            $query->where(function ($q) use ($types): void {
                foreach ($types as $type) {
                    $q->orWhere('data->type', trim($type));
                }
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($status === 'unread') {
                $query->whereNull('read_at');
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('data->message', 'like', "%{$search}%")
                    ->orWhere('data->request_title', 'like', "%{$search}%")
                    ->orWhere('data->file_name', 'like', "%{$search}%")
                    ->orWhere('data->actor', 'like', "%{$search}%");
            });
        }

        $notifications = $query->paginate(20)->withQueryString();

        $typeCounts = $this->notificationTypeCountsForUser($request);

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
            'filters' => [
                'type' => $request->input('type', ''),
                'status' => $request->input('status', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
                'search' => $request->input('search', ''),
            ],
            'typeCounts' => $typeCounts,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();
        $notification->refresh();

        $payload = $this->payloadFactory->read(
            (string) $notification->id,
            (string) $notification->read_at?->toJSON(),
            $user->unreadNotifications()->count()
        );
        $this->ssePublisher->publishNotificationUpdate((string) $user->id, $payload);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $readAt = now();
        $notificationIds = $user->unreadNotifications()->pluck('id')->all();

        if ($notificationIds !== []) {
            $user->unreadNotifications()->update(['read_at' => $readAt]);
        }

        $payload = $this->payloadFactory->allRead(
            array_map(static fn ($id): string => (string) $id, $notificationIds),
            $readAt->toJSON(),
            $user->unreadNotifications()->count()
        );
        $this->ssePublisher->publishNotificationUpdate((string) $user->id, $payload);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
