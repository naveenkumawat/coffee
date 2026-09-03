<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept Sanctum/web SPA session or staff admin session for notification APIs.
 */
class AuthenticateNotificationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin')
            ?? $request->user('web')
            ?? $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
