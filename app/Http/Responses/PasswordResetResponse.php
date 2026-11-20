<?php

namespace App\Http\Responses;

use App\Support\PostAuthenticationRedirector;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

class PasswordResetResponse implements PasswordResetResponseContract
{
    public function __construct(
        private readonly PostAuthenticationRedirector $postAuthenticationRedirector
    ) {}

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        // Defensive check - user should always be authenticated at this point
        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'Session expired. Please log in again.');
        }

        return $this->postAuthenticationRedirector->redirect($user);
    }
}
