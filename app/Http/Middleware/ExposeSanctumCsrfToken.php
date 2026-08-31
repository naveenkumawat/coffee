<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-origin SPAs (e.g. Vite on :4173, Laravel on :80) often cannot read the
 * XSRF-TOKEN cookie via document.cookie even when the browser stores it for API
 * requests. Expose the plain session token so the PWA can send X-CSRF-TOKEN.
 */
class ExposeSanctumCsrfToken
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->is('sanctum/csrf-cookie') || ! $request->hasSession()) {
            return $response;
        }

        $response->headers->set('X-CSRF-TOKEN', $request->session()->token());

        return $response;
    }
}
