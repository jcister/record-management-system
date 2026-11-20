<?php

namespace App\Observers;

use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class RoleObserver
{
    public function __construct(private readonly AuditService $audit) {}

    public function created(Role $role): void
    {
        $this->audit->log(
            'role_created',
            $this->actorPrefix().' created role "'.$role->name.'".',
            $role
        );
    }

    public function updated(Role $role): void
    {
        $log = $this->audit->logModelUpdate($role, 'role_updated', $role->getChanges());

        if (! $log->exists) {
            return;
        }

        $log->update([
            'description' => $this->actorPrefix().' updated role "'.$role->name.'".',
        ]);
    }

    public function deleted(Role $role): void
    {
        $this->audit->log(
            'role_deleted',
            $this->actorPrefix().' deleted role "'.$role->name.'".',
            $role
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
