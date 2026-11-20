<?php

namespace App\Observers;

use App\Models\Institution;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class InstitutionObserver
{
    public function __construct(private readonly AuditService $audit) {}

    public function created(Institution $institution): void
    {
        $this->audit->log(
            'institution_created',
            $this->actorPrefix().' added institution "'.$institution->name.'".',
            $institution
        );
    }

    public function updated(Institution $institution): void
    {
        $log = $this->audit->logModelUpdate($institution, 'institution_updated', $institution->getChanges());

        if (! $log->exists) {
            return;
        }

        $log->update([
            'description' => $this->actorPrefix().' updated institution "'.$institution->name.'".',
        ]);
    }

    public function deleted(Institution $institution): void
    {
        $this->audit->log(
            'institution_deleted',
            $this->actorPrefix().' deleted institution "'.$institution->name.'".',
            $institution
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
