<?php

namespace App\Listeners\Authentication;

use App\Services\AuditService;
use Illuminate\Auth\Events\Logout;

class LogoutListener
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        $this->audit->log(
            'logout',
            ($user?->name ?? AuditService::SYSTEM_ACTOR_NAME).' signed out.',
            $user,
            ['guard' => $event->guard]
        );
    }
}
