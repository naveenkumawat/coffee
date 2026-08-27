<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user('admin') ?? $request->user();

        abort_if(! $user, 401);

        $allowedRoles = collect($roles)
            ->map(fn (string $role) => UserRole::from($role))
            ->all();

        abort_unless($user->hasRole(...$allowedRoles), 403);

        return $next($request);
    }
}
