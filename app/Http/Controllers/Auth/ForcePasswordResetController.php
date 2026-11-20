<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\PostAuthenticationRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordResetController extends Controller
{
    use PasswordValidationRules;

    public function __construct(
        private readonly AuditService $audit,
        private readonly PostAuthenticationRedirector $postAuthenticationRedirector,
    ) {}

    /**
     * Show the force password reset form.
     */
    public function show(): Response
    {
        return Inertia::render('auth/ForcePasswordReset');
    }

    /**
     * Handle the force password reset request.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => $this->passwordRules(),
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
            'password_reset_required' => false,
        ]);

        // Regenerate session to prevent session fixation and update CSRF token
        $request->session()->regenerate();

        // Re-authenticate the user with the new session, rotating remember token if applicable
        $guard = Auth::guard('web');

        if ($guard->viaRemember()) {
            $guard->login($user, true);
        } else {
            $guard->login($user);
        }
        $this->audit->log(
            'password_changed',
            $user->name.' completed required password reset.',
            $user
        );

        return $this->postAuthenticationRedirector->redirect($user);
    }
}
