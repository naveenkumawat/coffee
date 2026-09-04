<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentServiceInterface $payments,
    ) {}

    public function razorpay(Request $request): JsonResponse
    {
        return $this->handle('razorpay', $request);
    }

    public function payu(Request $request): JsonResponse
    {
        return $this->handle('payu', $request);
    }

    public function paytm(Request $request): JsonResponse
    {
        return $this->handle('paytm', $request);
    }

    public function phonepe(Request $request): JsonResponse
    {
        return $this->handle('phonepe', $request);
    }

    protected function handle(string $provider, Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $headers = [];
        foreach (['X-Razorpay-Signature', 'X-VERIFY', 'x-razorpay-signature', 'x-verify'] as $header) {
            if ($request->headers->has($header)) {
                $headers[$header] = (string) $request->headers->get($header);
            }
        }

        try {
            $payload = $raw !== ''
                ? $raw
                : (json_encode($request->all(), JSON_UNESCAPED_SLASHES) ?: '{}');

            $result = $this->payments->handleWebhook(
                $provider,
                $payload,
                $headers,
                $request->all(),
            );
        } catch (\Throwable $exception) {
            Log::warning('payment.webhook_failed', [
                'provider' => $provider,
            ]);

            return response()->json(['ok' => false], 400);
        }

        return response()->json([
            'ok' => true,
            'duplicate' => (bool) ($result['duplicate'] ?? false),
            'result' => $result['result'] ?? null,
        ]);
    }
}
