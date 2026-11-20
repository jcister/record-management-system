<?php

namespace App\Observers;

use App\Models\Permission;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class PermissionObserver
{
    public function __construct(private readonly AuditService $audit) {}

    public function created(Permission $permission): void
    {
        $this->audit->log(
            'permission_created',
            $this->actorPrefix().' created permission "'.$permission->name.'".',
            $permission
        );
    }

    public function updated(Permission $permission): void
    {
        $log = $this->audit->logModelUpdate($permission, 'permission_updated', $permission->getChanges());

        if (! $log->exists) {
            return;
        }

        $log->update([
            'description' => $this->actorPrefix().' updated permission "'.$permission->name.'".',
        ]);
    }

    public function deleted(Permission $permission): void
    {
        $this->audit->log(
            'permission_deleted',
            $this->actorPrefix().' deleted permission "'.$permission->name.'".',
            $permission
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
