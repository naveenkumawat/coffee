<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payment\PaymentEligibilityServiceInterface;
use App\Services\Payment\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPaymentController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected PaymentEligibilityServiceInterface $eligibility,
        protected PaymentServiceInterface $payments,
    ) {}

    public function methods(Request $request): JsonResponse
    {
        $methods = $this->eligibility->methodsByFulfilment($request->user());

        $flat = [];
        foreach ($methods as $rows) {
            foreach ($rows as $row) {
                $flat[$row['key']] = $row;
            }
        }

        return $this->respondWithData([
            'by_fulfilment' => $methods,
            'methods' => array_values($flat),
        ], 'Payment methods retrieved.');
    }

    public function initiate(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $result = $this->payments->initiate($order, $request->user(), (string) $validated['payment_method']);

        return $this->respondWithData([
            'attempt_id' => $result['attempt']->getKey(),
            'provider' => $result['attempt']->provider,
            'status' => $result['attempt']->status?->value,
            'client' => $result['client'],
        ], 'Payment initiated.', 201);
    }

    public function verifyReturn(Request $request, PaymentAttempt $paymentAttempt): JsonResponse
    {
        $order = $paymentAttempt->order()->firstOrFail();
        $this->authorize('view', $order);

        $attempt = $this->payments->verifyReturn($paymentAttempt, $request->all());
        $order = $order->fresh();

        return $this->respondWithData([
            'attempt_id' => $attempt->getKey(),
            'status' => $attempt->status?->value,
            'order_status' => $order?->status?->value,
            'payment_status' => $order?->payment_status?->value,
            'order_id' => $order?->getKey(),
        ], 'Payment verification processed.');
    }
}
