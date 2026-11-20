<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordReset
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is authenticated and needs password reset
        if ($user && $user->password_reset_required) {
            // Allow API routes to proceed (they should handle this on the frontend)
            if ($request->is('api/*')) {
                return $next($request);
            }

            // Allow access to force password reset routes and logout
            if (! $request->routeIs('force-password-reset.*') && ! $request->routeIs('logout')) {
                return redirect()->route('force-password-reset.show');
            }
        }

        return $next($request);
    }
}
