<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnyAbility
{
    /**
     * Handle an incoming request and allow through if the user has any of the provided abilities.
     *
     * Usage in routes: ->middleware(['auth:sanctum', 'any_ability:upload-content|manage-requests'])
     */
    public function handle(Request $request, Closure $next, string $abilities = '')
    {
        if (! $abilities) {
            abort(403);
        }

        $list = array_values(array_filter(array_map('trim', preg_split('/[|,]/', $abilities))));

        if (empty($list)) {
            abort(403);
        }

        if (! Gate::any($list)) {
            abort(403);
        }

        return $next($request);
    }
}
