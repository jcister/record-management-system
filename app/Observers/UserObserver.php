<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    public function __construct(private readonly AuditService $audit) {}

    public function created(User $user): void
    {
        $this->audit->log(
            'user_created',
            $this->actorPrefix().' created account for "'.$user->name.'".',
            $user
        );
    }

    public function updated(User $user): void
    {
        $log = $this->audit->logModelUpdate($user, 'user_updated', $user->getChanges());

        if (! $log->exists) {
            return;
        }

        $log->update([
            'description' => $this->actorPrefix().' updated profile for "'.$user->name.'".',
        ]);
    }

    public function deleted(User $user): void
    {
        $this->audit->log(
            'user_deleted',
            $this->actorPrefix().' removed account for "'.$user->name.'".',
            $user
        );
    }

    private function actorPrefix(): string
    {
        $actor = Auth::user();

        if ($actor === null) {
            return AuditService::SYSTEM_ACTOR_NAME;
        }

        return $actor->name;
    }
}
