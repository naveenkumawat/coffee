<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;

interface PaymentServiceInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function methodsForOrder(Order $order, User $customer): array;

    /**
     * @return array{attempt: PaymentAttempt, client: array<string, mixed>}
     */
    public function initiate(Order $order, User $customer, string $paymentMethodKey): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyReturn(PaymentAttempt $attempt, array $payload): PaymentAttempt;

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleWebhook(string $provider, string $rawBody, array $headers, array $payload): array;
}
