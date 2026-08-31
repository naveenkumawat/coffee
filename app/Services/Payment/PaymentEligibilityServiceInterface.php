<?php

namespace App\Services\Payment;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\PaymentMethod;
use App\Models\User;

interface PaymentEligibilityServiceInterface
{
    /**
     * @return list<array{key: string, label: string, subtitle: string}>
     */
    public function methodsFor(User $customer, OrderFulfilmentMethod|string|null $fulfilment): array;

    /**
     * @return array<string, list<array{key: string, label: string, subtitle: string}>>
     */
    public function methodsByFulfilment(User $customer): array;

    public function isAllowed(User $customer, OrderFulfilmentMethod|string|null $fulfilment, PaymentMethod|string|null $paymentMethod): bool;

    public function cashAllowed(User $customer, OrderFulfilmentMethod $fulfilment): bool;

    public function assertAllowed(User $customer, OrderFulfilmentMethod|string|null $fulfilment, PaymentMethod|string|null $paymentMethod): PaymentMethod;
}
