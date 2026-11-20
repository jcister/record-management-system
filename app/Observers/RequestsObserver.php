<?php

namespace App\Observers;

use App\Models\Requests;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class RequestsObserver
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Requests $request): void
    {
        $this->audit->log(
            'request_created',
            $this->actorPrefix().' created request "'.$request->request_title.'".',
            $request,
            [
                'request_id' => $request->request_id,
                'status' => $request->status,
                'department_id' => $request->department_id,
            ]
        );

        $this->notifications->notifyNewRequest($request, Auth::user());
    }

    public function updated(Requests $request): void
    {
        $changes = $request->getChanges();
        $meaningful = array_diff_key($changes, array_flip(['updated_at']));
        $onlyStatusOrNotes = array_diff_key($meaningful, array_flip(['status', 'notes'])) === [];

        if ($onlyStatusOrNotes && array_key_exists('status', $changes)) {
            $oldStatus = $request->getOriginal('status');
            $newStatus = $changes['status'];

            $this->audit->log(
                'request_status_changed',
                $this->actorPrefix().' changed request "'.$request->request_title.'" status to '.$newStatus.'.',
                $request,
                [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]
            );

            $this->notifications->notifyRequestStatusUpdated($request, $oldStatus, $newStatus, Auth::user());

            return;
        }

        $log = $this->audit->logModelUpdate($request, 'request_updated', $changes);

        if (! $log->exists) {
            return;
        }

        $log->update([
            'description' => $this->actorPrefix().' updated request "'.$request->request_title.'".',
        ]);
    }

    public function deleted(Requests $request): void
    {
        $this->audit->log(
            'request_deleted',
            $this->actorPrefix().' deleted request "'.$request->request_title.'".',
            $request,
            [
                'request_id' => $request->request_id,
                'status' => $request->status,
            ]
        );
    }

    public function restored(Requests $request): void
    {
        $this->audit->log(
            'request_restored',
            $this->actorPrefix().' restored request "'.$request->request_title.'".',
            $request,
            [
                'request_id' => $request->request_id,
            ]
        );
    }

    public function forceDeleted(Requests $request): void
    {
        $this->audit->log(
            'request_force_deleted',
            $this->actorPrefix().' permanently deleted request "'.$request->request_title.'".',
            $request,
            [
                'request_id' => $request->request_id,
            ]
        );
    }

    private function actorPrefix(): string
    {
        $user = Auth::user();

        if ($user === null) {
            return AuditService::SYSTEM_ACTOR_NAME;
        }

        return $user->name;
    }
}
