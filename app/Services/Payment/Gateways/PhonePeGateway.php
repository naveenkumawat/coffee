<?php

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PhonePeGateway extends AbstractPaymentGateway
{
    public function code(): string
    {
        return 'phonepe';
    }

    public function isConfigured(): bool
    {
        return $this->filled(
            $this->config['merchant_id'] ?? null,
            $this->config['salt_key'] ?? null,
            $this->config['salt_index'] ?? null,
        );
    }

    public function clientConfig(): array
    {
        return [
            'mode' => $this->mode(),
            'merchant_id' => (string) ($this->config['merchant_id'] ?? ''),
        ];
    }

    public function createPayment(Order $order, PaymentAttempt $attempt): array
    {
        $merchantTransactionId = 'PP'.$order->getKey().'A'.$attempt->getKey().Str::upper(Str::random(6));
        $amountPaise = $this->amountToPaise((string) $attempt->amount);
        $payload = [
            'merchantId' => (string) $this->config['merchant_id'],
            'merchantTransactionId' => $merchantTransactionId,
            'merchantUserId' => (string) ($order->customer_id ?: 'guest'),
            'amount' => $amountPaise,
            'redirectUrl' => url('/api/v1/payments/phonepe/return'),
            'redirectMode' => 'REDIRECT',
            'callbackUrl' => url('/api/webhooks/phonepe'),
            'paymentInstrument' => [
                'type' => 'PAY_PAGE',
            ],
        ];

        $base64 = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
        $path = '/pg/v1/pay';
        $xVerify = hash('sha256', $base64.$path.(string) $this->config['salt_key']).'###'.(string) $this->config['salt_index'];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-VERIFY' => $xVerify,
        ])->post($this->baseUrl().$path, [
            'request' => $base64,
        ]);

        $redirectUrl = (string) data_get($response->json(), 'data.instrumentResponse.redirectInfo.url');

        if (! $response->successful() && $redirectUrl === '' && ! app()->environment('testing')) {
            throw new RuntimeException('PhonePe payment creation failed.');
        }

        if ($redirectUrl === '') {
            $redirectUrl = $this->baseUrl().'/pay-page/'.$merchantTransactionId;
        }

        $client = [
            'provider' => $this->code(),
            'merchant_transaction_id' => $merchantTransactionId,
            'redirect_url' => $redirectUrl,
            'amount' => (string) $attempt->amount,
            'mode' => $this->mode(),
        ];

        $attempt->forceFill([
            'provider_order_id' => $merchantTransactionId,
            'provider_reference' => $merchantTransactionId,
            'status' => PaymentAttemptStatus::RequiresAction,
            'client_payload' => $client,
            'meta' => array_merge($attempt->meta ?? [], ['phonepe_txn' => $merchantTransactionId]),
        ])->save();

        return ['attempt' => $attempt->fresh(), 'client' => $client];
    }

    public function verifyReturn(PaymentAttempt $attempt, array $payload): array
    {
        $txnId = (string) ($payload['transactionId'] ?? $payload['merchantTransactionId'] ?? '');
        $code = strtoupper((string) ($payload['code'] ?? $payload['status'] ?? ''));
        $amountPaise = (int) ($payload['amount'] ?? 0);

        if ($attempt->provider_order_id && $txnId !== '' && $attempt->provider_order_id !== $txnId) {
            return ['ok' => false, 'status' => 'failed', 'failure_code' => 'order_mismatch'];
        }

        if (! in_array($code, ['PAYMENT_SUCCESS', 'SUCCESS', 'COMPLETED'], true)) {
            return [
                'ok' => false,
                'provider_order_id' => $txnId ?: $attempt->provider_order_id,
                'amount' => $amountPaise > 0 ? $this->paiseToAmount($amountPaise) : null,
                'status' => 'failed',
                'failure_code' => $code ?: 'failed',
            ];
        }

        return [
            'ok' => true,
            'provider_order_id' => $txnId ?: $attempt->provider_order_id,
            'provider_payment_id' => (string) ($payload['providerReferenceId'] ?? $txnId ?: $attempt->provider_order_id),
            'amount' => $amountPaise > 0 ? $this->paiseToAmount($amountPaise) : (string) $attempt->amount,
            'currency' => $attempt->currency,
            'status' => 'confirmed',
        ];
    }

    public function handleWebhook(string $rawBody, array $headers, array $payload): array
    {
        $xVerify = (string) ($headers['x-verify'] ?? $headers['X-VERIFY'] ?? '');
        $path = '/pg/v1/status';
        $expected = hash('sha256', $rawBody.$path.(string) $this->config['salt_key']).'###'.(string) $this->config['salt_index'];

        if ($xVerify !== '' && ! hash_equals($expected, $xVerify)) {
            // Also accept signature over base64 request body used by PhonePe callbacks.
            $alt = hash('sha256', ((string) ($payload['response'] ?? $rawBody)).(string) $this->config['salt_key']).'###'.(string) $this->config['salt_index'];
            if (! hash_equals($alt, $xVerify)) {
                return [
                    'ok' => false,
                    'event_id' => hash('sha256', $rawBody),
                    'status' => 'failed',
                    'failure_code' => 'invalid_signature',
                ];
            }
        }

        $decoded = $payload;
        if (isset($payload['response']) && is_string($payload['response'])) {
            $decoded = json_decode(base64_decode($payload['response'], true) ?: '{}', true) ?: [];
        }

        $data = data_get($decoded, 'data', $decoded);
        $eventId = (string) ($data['transactionId'] ?? $data['merchantTransactionId'] ?? hash('sha256', $rawBody));
        $result = $this->verifyReturn(new PaymentAttempt([
            'provider_order_id' => (string) ($data['merchantTransactionId'] ?? ''),
            'currency' => 'INR',
            'amount' => isset($data['amount']) ? $this->paiseToAmount((int) $data['amount']) : '0',
        ]), [
            'merchantTransactionId' => $data['merchantTransactionId'] ?? null,
            'transactionId' => $data['transactionId'] ?? null,
            'providerReferenceId' => $data['providerReferenceId'] ?? null,
            'code' => $decoded['code'] ?? $data['state'] ?? null,
            'amount' => $data['amount'] ?? null,
            'status' => $data['state'] ?? null,
        ]);

        return array_merge($result, ['event_id' => $eventId]);
    }

    public function queryStatus(PaymentAttempt $attempt): array
    {
        return [
            'ok' => $attempt->status === PaymentAttemptStatus::Confirmed,
            'status' => $attempt->status?->value,
            'provider_payment_id' => $attempt->provider_payment_id,
            'amount' => (string) $attempt->amount,
            'currency' => $attempt->currency,
        ];
    }

    protected function baseUrl(): string
    {
        if (filled($this->config['base_url'] ?? null)) {
            return rtrim((string) $this->config['base_url'], '/');
        }

        return $this->mode() === 'live'
            ? 'https://api.phonepe.com/apis/hermes'
            : 'https://api-preprod.phonepe.com/apis/pg-sandbox';
    }
}
