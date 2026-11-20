<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PostAuthenticationRedirector
{
    public function __construct(
        private readonly string $dashboardPermission = 'organize-content',
        private readonly string $dashboardRoute = 'dashboard',
        private readonly string $fallbackRoute = 'requests.myrequests',
    ) {}

    public function resolve(User $user): string
    {
        if ($user->can($this->dashboardPermission)) {
            return $this->dashboardRoute;
        }

        return $this->fallbackRoute;
    }

    public function redirect(User $user): RedirectResponse
    {
        return redirect()->route($this->resolve($user));
    }
}
