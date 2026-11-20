<?php

namespace App\Listeners\Authentication;

use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;

class FailedLoginListener
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'unknown';

        $this->audit->log(
            'login_failed',
            'Failed sign-in attempt for '.$email.'.',
            $event->user,
            ['email' => $email]
        );
    }
}
