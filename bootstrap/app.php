<?php

use App\Http\Middleware\AddRequestContext;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ExposeSanctumCsrfToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddRequestContext::class);
        $middleware->appendToGroup('web', ExposeSanctumCsrfToken::class);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(function (Request $request): string {
            $routeName = (string) ($request->route()?->getName() ?? '');

            if (str_starts_with($routeName, 'customer.')) {
                return route('customer.login');
            }

            return str_starts_with($routeName, 'barista.')
                ? route('barista.login')
                : route('administrator.login');
        });
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $exception->errors(),
            ], $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
                'errors' => [],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage() ?: 'This action is unauthorized.',
                'errors' => [],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'Resource not found.',
                'errors' => [],
            ], 404);
        });

        $exceptions->report(function (Throwable $exception): void {
            Log::error($exception->getMessage(), [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            if (config('app.debug') || app()->environment(['local', 'testing'])) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

            if (view()->exists("errors.{$status}")) {
                return response()->view("errors.{$status}", ['exception' => $exception], $status);
            }

            return response()->view('errors.500', ['exception' => $exception], $status);
        });
    })->create();
