<?php

namespace App\Providers;

use App\Listeners\Authentication\FailedLoginListener;
use App\Listeners\Authentication\LoginListener;
use App\Listeners\Authentication\LogoutListener;
use App\Listeners\Authentication\PasswordResetListener;
use App\Listeners\Authentication\VerifiedListener;
use App\Listeners\Notifications\PublishNotificationSseUpdate;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationSent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Login::class => [
            LoginListener::class,
        ],
        Logout::class => [
            LogoutListener::class,
        ],
        Failed::class => [
            FailedLoginListener::class,
        ],
        PasswordReset::class => [
            PasswordResetListener::class,
        ],
        Verified::class => [
            VerifiedListener::class,
        ],
        NotificationSent::class => [
            PublishNotificationSseUpdate::class,
        ],
    ];
}
