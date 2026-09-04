<?php

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RazorpayGateway extends AbstractPaymentGateway
{
    public function code(): string
    {
        return 'razorpay';
    }

    public function isConfigured(): bool
    {
        return $this->filled($this->config['key_id'] ?? null, $this->config['key_secret'] ?? null);
    }

    public function clientConfig(): array
    {
        return [
            'key_id' => (string) ($this->config['key_id'] ?? ''),
            'mode' => $this->mode(),
        ];
    }

    public function createPayment(Order $order, PaymentAttempt $attempt): array
    {
        $response = Http::withBasicAuth((string) $this->config['key_id'], (string) $this->config['key_secret'])
            ->acceptJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $this->amountToPaise((string) $attempt->amount),
                'currency' => $attempt->currency,
                'receipt' => $order->order_number,
                'notes' => [
                    'order_id' => (string) $order->getKey(),
                    'attempt_id' => (string) $attempt->getKey(),
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Razorpay order creation failed.');
        }

        $providerOrderId = (string) $response->json('id');
        $attempt->forceFill([
            'provider_order_id' => $providerOrderId,
            'status' => PaymentAttemptStatus::RequiresAction,
            'client_payload' => [
                'provider' => $this->code(),
                'key_id' => $this->config['key_id'],
                'order_id' => $providerOrderId,
                'amount' => $this->amountToPaise((string) $attempt->amount),
                'currency' => $attempt->currency,
                'name' => config('coffee.payments.display_name'),
                'description' => 'Order '.$order->order_number,
                'prefill' => [
                    'name' => $order->customer_name,
                    'email' => $order->customer_email,
                    'contact' => $order->customer_phone,
                ],
            ],
            'meta' => array_merge($attempt->meta ?? [], [
                'razorpay_order' => [
                    'id' => $providerOrderId,
                    'amount' => $response->json('amount'),
                    'currency' => $response->json('currency'),
                ],
            ]),
        ])->save();

        return [
            'attempt' => $attempt->fresh(),
            'client' => $attempt->client_payload ?? [],
        ];
    }

    public function verifyReturn(PaymentAttempt $attempt, array $payload): array
    {
        $orderId = (string) ($payload['razorpay_order_id'] ?? '');
        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        $signature = (string) ($payload['razorpay_signature'] ?? '');

        if ($orderId === '' || $paymentId === '' || $signature === '') {
            return ['ok' => false, 'status' => 'failed', 'failure_code' => 'missing_fields'];
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) $this->config['key_secret']);

        if (! hash_equals($expected, $signature)) {
            return ['ok' => false, 'status' => 'failed', 'failure_code' => 'invalid_signature'];
        }

        if ($attempt->provider_order_id && $attempt->provider_order_id !== $orderId) {
            return ['ok' => false, 'status' => 'failed', 'failure_code' => 'order_mismatch'];
        }

        return [
            'ok' => true,
            'provider_payment_id' => $paymentId,
            'provider_order_id' => $orderId,
            'amount' => (string) $attempt->amount,
            'currency' => $attempt->currency,
            'status' => 'confirmed',
        ];
    }

    public function handleWebhook(string $rawBody, array $headers, array $payload): array
    {
        $signature = (string) ($headers['x-razorpay-signature'] ?? $headers['X-Razorpay-Signature'] ?? '');
        $secret = (string) ($this->config['webhook_secret'] ?: $this->config['key_secret']);
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return [
                'ok' => false,
                'event_id' => (string) ($payload['event'] ?? 'invalid').':'.Str::substr(hash('sha256', $rawBody), 0, 16),
                'status' => 'failed',
                'failure_code' => 'invalid_signature',
            ];
        }

        $event = (string) ($payload['event'] ?? 'unknown');
        $paymentEntity = data_get($payload, 'payload.payment.entity', []);
        $eventId = (string) ($payload['id'] ?? hash('sha256', $rawBody));

        if ($event !== 'payment.captured' && data_get($paymentEntity, 'status') !== 'captured') {
            return [
                'ok' => false,
                'event_id' => $eventId,
                'status' => 'ignored',
                'failure_code' => 'ignored_event',
            ];
        }

        $amountPaise = (int) ($paymentEntity['amount'] ?? 0);

        return [
            'ok' => true,
            'event_id' => $eventId,
            'provider_payment_id' => isset($paymentEntity['id']) ? (string) $paymentEntity['id'] : null,
            'provider_order_id' => isset($paymentEntity['order_id']) ? (string) $paymentEntity['order_id'] : null,
            'amount' => $amountPaise > 0 ? $this->paiseToAmount($amountPaise) : null,
            'currency' => isset($paymentEntity['currency']) ? (string) $paymentEntity['currency'] : null,
            'status' => 'confirmed',
            'meta' => ['event' => $event],
        ];
    }

    public function queryStatus(PaymentAttempt $attempt): array
    {
        if (! filled($attempt->provider_payment_id)) {
            return ['ok' => false, 'status' => 'pending'];
        }

        $response = Http::withBasicAuth((string) $this->config['key_id'], (string) $this->config['key_secret'])
            ->acceptJson()
            ->get('https://api.razorpay.com/v1/payments/'.$attempt->provider_payment_id);

        if (! $response->successful()) {
            return ['ok' => false, 'status' => 'failed'];
        }

        $status = (string) $response->json('status');
        $amountPaise = (int) $response->json('amount');

        return [
            'ok' => $status === 'captured',
            'status' => $status === 'captured' ? 'confirmed' : $status,
            'provider_payment_id' => (string) $response->json('id'),
            'amount' => $this->paiseToAmount($amountPaise),
            'currency' => (string) $response->json('currency'),
        ];
    }
}
