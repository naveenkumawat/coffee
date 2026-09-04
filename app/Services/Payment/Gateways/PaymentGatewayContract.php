<?php

namespace App\Services\Payment\Gateways;

use App\Models\Order;
use App\Models\PaymentAttempt;

interface PaymentGatewayContract
{
    public function code(): string;

    public function isConfigured(): bool;

    public function mode(): string;

    /**
     * @return array{attempt: PaymentAttempt, client: array<string, mixed>}
     */
    public function createPayment(Order $order, PaymentAttempt $attempt): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, provider_payment_id?: ?string, provider_order_id?: ?string, amount?: ?string, currency?: ?string, status?: string, failure_code?: ?string, failure_message?: ?string, meta?: array<string, mixed>}
     */
    public function verifyReturn(PaymentAttempt $attempt, array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, event_id: string, provider_payment_id?: ?string, provider_order_id?: ?string, amount?: ?string, currency?: ?string, status?: string, failure_code?: ?string, failure_message?: ?string, meta?: array<string, mixed>}
     */
    public function handleWebhook(string $rawBody, array $headers, array $payload): array;

    /**
     * @return array{ok: bool, status?: string, provider_payment_id?: ?string, amount?: ?string, currency?: ?string, meta?: array<string, mixed>}
     */
    public function queryStatus(PaymentAttempt $attempt): array;

    /**
     * Customer-safe public config (never secrets).
     *
     * @return array<string, mixed>
     */
    public function clientConfig(): array;
}
