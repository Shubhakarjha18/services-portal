<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard by role name, e.g. ->middleware('role:officer,admin').
 *
 * Kept separate from policies: this protects whole route groups cheaply, while
 * policies still decide row-level access inside them.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->is_active) {
            abort(403, 'This account has been deactivated.');
        }

        if ($roles !== [] && ! $user->hasRole(...$roles)) {
            abort(403, 'Your role is not permitted to access this resource.');
        }

        return $next($request);
    }
}
