<?php

namespace App\Observers;

use App\Models\Department;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class DepartmentObserver
{
    public function __construct(private readonly AuditService $audit) {}

    public function created(Department $department): void
    {
        $this->audit->log(
            'department_created',
            $this->actorPrefix().' created department "'.$department->name.'".',
            $department
        );
    }

    public function updated(Department $department): void
    {
        $log = $this->audit->logModelUpdate($department, 'department_updated', $department->getChanges());

        if (! $log->exists) {
            return;
        }

        $log->update([
            'description' => $this->actorPrefix().' updated department "'.$department->name.'".',
        ]);
    }

    public function deleted(Department $department): void
    {
        $this->audit->log(
            'department_deleted',
            $this->actorPrefix().' deleted department "'.$department->name.'".',
            $department
        );
    }

    public function restored(Department $department): void
    {
        $this->audit->log(
            'department_restored',
            $this->actorPrefix().' restored department "'.$department->name.'".',
            $department
        );
    }

    public function forceDeleted(Department $department): void
    {
        $this->audit->log(
            'department_force_deleted',
            $this->actorPrefix().' permanently deleted department "'.$department->name.'".',
            $department
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
