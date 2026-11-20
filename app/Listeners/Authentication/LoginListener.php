<?php

namespace App\Listeners\Authentication;

use App\Services\AuditService;
use Illuminate\Auth\Events\Login;

class LoginListener
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        $this->audit->log(
            'login_success',
            ($user?->name ?? AuditService::SYSTEM_ACTOR_NAME).' signed in.',
            $user,
            ['guard' => $event->guard]
        );
    }
}
