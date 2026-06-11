<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse section gate (e.g. /admin, /instructor). Resource-level decisions
 * stay in policies — this middleware only keeps whole sections out of reach
 * of the wrong role.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->role === UserRole::from($role),
            403,
        );

        return $next($request);
    }
}
