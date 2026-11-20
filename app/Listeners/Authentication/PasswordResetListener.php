<?php

namespace App\Listeners\Authentication;

use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;

class PasswordResetListener
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        $this->audit->log(
            'password_reset',
            ($user?->name ?? AuditService::SYSTEM_ACTOR_NAME).' reset their password.',
            $user
        );
    }
}
