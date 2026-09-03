<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept either the customer/SPA (web) or staff (admin) session for broadcast auth.
 */
class AuthenticateBroadcastRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('web') || $request->user('admin')) {
            return $next($request);
        }

        abort(401, 'Unauthenticated.');
    }
}
