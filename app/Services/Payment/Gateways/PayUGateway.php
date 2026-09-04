<?php

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Support\Str;

class PayUGateway extends AbstractPaymentGateway
{
    public function code(): string
    {
        return 'payu';
    }

    public function isConfigured(): bool
    {
        return $this->filled($this->config['merchant_key'] ?? null, $this->config['merchant_salt'] ?? null);
    }

    public function clientConfig(): array
    {
        return [
            'mode' => $this->mode(),
            'action_url' => $this->actionUrl(),
        ];
    }

    public function createPayment(Order $order, PaymentAttempt $attempt): array
    {
        $txnId = 'PU'.Str::upper(Str::random(12)).$attempt->getKey();
        $amount = number_format((float) $attempt->amount, 2, '.', '');
        $productInfo = 'Order '.$order->order_number;
        $firstName = trim((string) Str::before((string) $order->customer_name, ' ')) ?: 'Customer';
        $email = (string) ($order->customer_email ?: 'customer@example.com');
        $phone = (string) ($order->customer_phone ?: '9999999999');
        $key = (string) $this->config['merchant_key'];
        $salt = (string) $this->config['merchant_salt'];
        $hashSequence = implode('|', [
            $key,
            $txnId,
            $amount,
            $productInfo,
            $firstName,
            $email,
            (string) $order->getKey(),
            (string) $attempt->getKey(),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $salt,
        ]);
        $hash = strtolower(hash('sha512', $hashSequence));

        $client = [
            'provider' => $this->code(),
            'action_url' => $this->actionUrl(),
            'fields' => [
                'key' => $key,
                'txnid' => $txnId,
                'amount' => $amount,
                'productinfo' => $productInfo,
                'firstname' => $firstName,
                'email' => $email,
                'phone' => $phone,
                'surl' => url('/api/v1/payments/payu/return'),
                'furl' => url('/api/v1/payments/payu/return'),
                'hash' => $hash,
                'udf1' => (string) $order->getKey(),
                'udf2' => (string) $attempt->getKey(),
            ],
        ];

        $attempt->forceFill([
            'provider_order_id' => $txnId,
            'provider_reference' => $txnId,
            'status' => PaymentAttemptStatus::RequiresAction,
            'client_payload' => $client,
            'meta' => array_merge($attempt->meta ?? [], ['payu_txnid' => $txnId]),
        ])->save();

        return ['attempt' => $attempt->fresh(), 'client' => $client];
    }

    public function verifyReturn(PaymentAttempt $attempt, array $payload): array
    {
        $status = strtolower((string) ($payload['status'] ?? ''));
        $txnId = (string) ($payload['txnid'] ?? '');
        $amount = (string) ($payload['amount'] ?? '');
        $hash = (string) ($payload['hash'] ?? '');
        $key = (string) $this->config['merchant_key'];
        $salt = (string) $this->config['merchant_salt'];

        $reverse = implode('|', [
            $salt,
            $status,
            '',
            '',
            '',
            '',
            '',
            (string) ($payload['udf5'] ?? ''),
            (string) ($payload['udf4'] ?? ''),
            (string) ($payload['udf3'] ?? ''),
            (string) ($payload['udf2'] ?? ''),
            (string) ($payload['udf1'] ?? ''),
            (string) ($payload['email'] ?? ''),
            (string) ($payload['firstname'] ?? ''),
            (string) ($payload['productinfo'] ?? ''),
            $amount,
            $txnId,
            $key,
        ]);

        if ($hash === '' || ! hash_equals(strtolower(hash('sha512', $reverse)), strtolower($hash))) {
            return ['ok' => false, 'status' => 'failed', 'failure_code' => 'invalid_signature'];
        }

        if ($attempt->provider_order_id && $attempt->provider_order_id !== $txnId) {
            return ['ok' => false, 'status' => 'failed', 'failure_code' => 'order_mismatch'];
        }

        if ($status !== 'success') {
            return [
                'ok' => false,
                'provider_order_id' => $txnId,
                'provider_payment_id' => isset($payload['mihpayid']) ? (string) $payload['mihpayid'] : null,
                'amount' => $amount !== '' ? number_format((float) $amount, 2, '.', '') : null,
                'status' => 'failed',
                'failure_code' => $status ?: 'failed',
            ];
        }

        return [
            'ok' => true,
            'provider_order_id' => $txnId,
            'provider_payment_id' => isset($payload['mihpayid']) ? (string) $payload['mihpayid'] : $txnId,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'currency' => $attempt->currency,
            'status' => 'confirmed',
        ];
    }

    public function handleWebhook(string $rawBody, array $headers, array $payload): array
    {
        $eventId = (string) ($payload['mihpayid'] ?? $payload['txnid'] ?? hash('sha256', $rawBody));
        $result = $this->verifyReturn(new PaymentAttempt([
            'provider_order_id' => (string) ($payload['txnid'] ?? ''),
            'currency' => 'INR',
            'amount' => (string) ($payload['amount'] ?? '0'),
        ]), $payload);

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

    protected function actionUrl(): string
    {
        if (filled($this->config['base_url'] ?? null)) {
            return rtrim((string) $this->config['base_url'], '/').'/_payment';
        }

        return $this->mode() === 'live'
            ? 'https://secure.payu.in/_payment'
            : 'https://test.payu.in/_payment';
    }
}
