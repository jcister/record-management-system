<?php

namespace App\Listeners\Authentication;

use App\Services\AuditService;
use Illuminate\Auth\Events\Verified;

class VerifiedListener
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        $this->audit->log(
            'email_verified',
            ($user?->name ?? AuditService::SYSTEM_ACTOR_NAME).' verified their email address.',
            $user
        );
    }
}
