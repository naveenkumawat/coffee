<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderSecurityException extends Exception
{
    public function __construct(
        public readonly string $securityCode,
        string $message,
        public readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        public readonly string $errorKey = 'ordering',
    ) {
        parent::__construct($message);
    }

    public function report(): bool
    {
        return false;
    }

    public function render(Request $request): JsonResponse|RedirectResponse|null
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => $this->securityCode,
                'errors' => [
                    $this->errorKey => [$this->getMessage()],
                ],
            ], $this->status);
        }

        return redirect()
            ->back()
            ->withInput()
            ->withErrors([
                $this->errorKey => $this->getMessage(),
            ]);
    }
}
