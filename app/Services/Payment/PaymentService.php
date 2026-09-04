<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use App\Services\Order\OrderServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        protected PaymentMethodCatalog $catalog,
        protected PaymentGatewayManager $gateways,
        protected PaymentEligibilityServiceInterface $eligibility,
        protected OrderServiceInterface $orders,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function methodsForOrder(Order $order, User $customer): array
    {
        $fulfilment = $order->fulfilment_method;

        if ($fulfilment === null) {
            return [];
        }

        return $this->catalog->availableMethods($customer, $fulfilment);
    }

    /**
     * @return array{attempt: PaymentAttempt, client: array<string, mixed>}
     */
    public function initiate(Order $order, User $customer, string $paymentMethodKey): array
    {
        /** @var array{order: Order, method: PaymentMethod, attempt: PaymentAttempt} $prepared */
        $prepared = DB::transaction(function () use ($order, $customer, $paymentMethodKey): array {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $locked->customer_id !== (int) $customer->getKey()) {
                throw ValidationException::withMessages([
                    'order' => 'This order does not belong to the authenticated customer.',
                ]);
            }

            $this->assertOrderPayable($locked);

            $method = $this->eligibility->assertAllowed(
                $customer,
                $locked->fulfilment_method,
                $paymentMethodKey,
            );

            if (! $method->requiresGatewayInitiation()) {
                throw ValidationException::withMessages([
                    'payment_method' => 'This payment method does not require online initiation.',
                ]);
            }

            $locked->forceFill([
                'payment_method' => $method->value,
            ])->save();

            $attempt = PaymentAttempt::query()->create([
                'order_id' => $locked->getKey(),
                'customer_id' => $customer->getKey(),
                'provider' => $method->apiKey(),
                'amount' => $locked->total_amount,
                'currency' => (string) config('coffee.payments.currency', 'INR'),
                'status' => PaymentAttemptStatus::Pending,
                'initiated_at' => now(),
            ]);

            return [
                'order' => $locked->fresh(),
                'method' => $method,
                'attempt' => $attempt,
            ];
        });

        try {
            return $this->gateways->gateway($prepared['method'])->createPayment(
                $prepared['order'],
                $prepared['attempt'],
            );
        } catch (Throwable $exception) {
            $prepared['attempt']->forceFill([
                'status' => PaymentAttemptStatus::Failed,
                'failed_at' => now(),
                'failure_code' => 'initiation_failed',
                'failure_message' => 'Unable to start payment with the selected provider.',
            ])->save();

            Log::warning('payment.initiation_failed', [
                'provider' => $prepared['method']->apiKey(),
                'order_id' => $prepared['order']->getKey(),
                'attempt_id' => $prepared['attempt']->getKey(),
            ]);

            throw ValidationException::withMessages([
                'payment_method' => 'Unable to start online payment. Please try another method.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyReturn(PaymentAttempt $attempt, array $payload): PaymentAttempt
    {
        $gateway = $this->gateways->gateway($attempt->provider);
        $result = $gateway->verifyReturn($attempt, $payload);

        return $this->applyProviderResult($attempt, $result, source: 'return');
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(string $provider, string $rawBody, array $headers, array $payload): array
    {
        $gateway = $this->gateways->gateway($provider);
        $result = $gateway->handleWebhook($rawBody, $headers, $payload);
        $eventId = (string) ($result['event_id'] ?? hash('sha256', $rawBody));

        $existing = PaymentWebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();

        if ($existing !== null) {
            return [
                'duplicate' => true,
                'result' => $existing->processing_result,
                'attempt_id' => $existing->payment_attempt_id,
            ];
        }

        $attempt = $this->findAttemptForProviderResult($provider, $result);

        if ($attempt === null) {
            PaymentWebhookEvent::query()->create([
                'provider' => $provider,
                'event_id' => $eventId,
                'payload_hash' => hash('sha256', $rawBody),
                'processing_result' => 'attempt_not_found',
            ]);

            return ['duplicate' => false, 'result' => 'attempt_not_found'];
        }

        $updated = $this->applyProviderResult($attempt, $result, source: 'webhook');

        PaymentWebhookEvent::query()->create([
            'provider' => $provider,
            'event_id' => $eventId,
            'payload_hash' => hash('sha256', $rawBody),
            'payment_attempt_id' => $updated->getKey(),
            'processing_result' => ($result['ok'] ?? false) ? 'confirmed' : (($result['status'] ?? 'failed')),
        ]);

        return [
            'duplicate' => false,
            'result' => $updated->status?->value,
            'attempt_id' => $updated->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function applyProviderResult(PaymentAttempt $attempt, array $result, string $source): PaymentAttempt
    {
        return DB::transaction(function () use ($attempt, $result, $source): PaymentAttempt {
            /** @var PaymentAttempt $locked */
            $locked = PaymentAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentAttemptStatus::Confirmed) {
                return $locked;
            }

            if (! ($result['ok'] ?? false)) {
                if (($result['status'] ?? null) === 'ignored') {
                    return $locked;
                }

                $locked->forceFill([
                    'status' => PaymentAttemptStatus::Failed,
                    'failed_at' => $locked->failed_at ?: now(),
                    'failure_code' => $result['failure_code'] ?? 'provider_failed',
                    'failure_message' => $result['failure_message'] ?? 'Payment was not successful.',
                    'provider_payment_id' => $result['provider_payment_id'] ?? $locked->provider_payment_id,
                    'provider_order_id' => $result['provider_order_id'] ?? $locked->provider_order_id,
                    'meta' => array_merge($locked->meta ?? [], [
                        'last_source' => $source,
                        'last_result' => [
                            'status' => $result['status'] ?? null,
                            'failure_code' => $result['failure_code'] ?? null,
                        ],
                    ]),
                ])->save();

                return $locked->fresh();
            }

            /** @var Order $order */
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            $expectedAmount = number_format((float) $order->total_amount, 2, '.', '');
            $providerAmount = isset($result['amount'])
                ? number_format((float) $result['amount'], 2, '.', '')
                : $expectedAmount;
            $currency = strtoupper((string) ($result['currency'] ?? $locked->currency));

            if ($providerAmount !== $expectedAmount || $currency !== strtoupper((string) $locked->currency)) {
                $locked->forceFill([
                    'status' => PaymentAttemptStatus::Failed,
                    'failed_at' => now(),
                    'failure_code' => 'amount_mismatch',
                    'failure_message' => 'Provider amount does not match the order total.',
                    'meta' => array_merge($locked->meta ?? [], [
                        'reconciliation' => [
                            'expected_amount' => $expectedAmount,
                            'provider_amount' => $providerAmount,
                            'currency' => $currency,
                            'source' => $source,
                        ],
                    ]),
                ])->save();

                Log::warning('payment.amount_mismatch', [
                    'order_id' => $order->getKey(),
                    'attempt_id' => $locked->getKey(),
                    'provider' => $locked->provider,
                ]);

                return $locked->fresh();
            }

            $locked->forceFill([
                'provider_payment_id' => $result['provider_payment_id'] ?? $locked->provider_payment_id,
                'provider_order_id' => $result['provider_order_id'] ?? $locked->provider_order_id,
                'status' => PaymentAttemptStatus::Confirmed,
                'confirmed_at' => $locked->confirmed_at ?: now(),
                'meta' => array_merge($locked->meta ?? [], [
                    'confirmed_via' => $source,
                ]),
            ])->save();

            if ($order->payment_status === PaymentStatus::Confirmed) {
                return $locked->fresh();
            }

            if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                $locked->forceFill([
                    'meta' => array_merge($locked->meta ?? [], [
                        'reconciliation' => [
                            'needs_admin' => true,
                            'reason' => 'payment_after_cancellation',
                            'source' => $source,
                        ],
                    ]),
                ])->save();

                Log::warning('payment.after_cancellation', [
                    'order_id' => $order->getKey(),
                    'attempt_id' => $locked->getKey(),
                    'provider' => $locked->provider,
                ]);

                return $locked->fresh();
            }

            $this->orders->confirmGatewayPayment(
                $order,
                $locked->fresh(),
                PaymentMethod::tryFromApiKey($locked->provider) ?? PaymentMethod::Razorpay,
            );

            return $locked->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function findAttemptForProviderResult(string $provider, array $result): ?PaymentAttempt
    {
        $paymentId = $result['provider_payment_id'] ?? null;
        $orderId = $result['provider_order_id'] ?? null;

        if (filled($paymentId)) {
            $byPayment = PaymentAttempt::query()
                ->where('provider', $provider)
                ->where('provider_payment_id', $paymentId)
                ->first();

            if ($byPayment !== null) {
                return $byPayment;
            }
        }

        if (filled($orderId)) {
            return PaymentAttempt::query()
                ->where('provider', $provider)
                ->where('provider_order_id', $orderId)
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }

    protected function assertOrderPayable(Order $order): void
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            throw ValidationException::withMessages([
                'order' => 'This order is not awaiting payment.',
            ]);
        }

        if ($order->payment_status === PaymentStatus::Confirmed || $order->payment_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'order' => 'This order is already paid.',
            ]);
        }

        if ($order->isPaymentWindowExpired()) {
            throw ValidationException::withMessages([
                'order' => 'The payment window for this order has expired.',
            ]);
        }
    }
}
