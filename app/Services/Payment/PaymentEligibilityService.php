<?php

namespace App\Services\Payment;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PaymentEligibilityService implements PaymentEligibilityServiceInterface
{
    public function __construct(
        protected PaymentMethodCatalog $catalog,
    ) {}

    /**
     * @return list<array{key: string, label: string, subtitle: string, code?: string, type?: string, available?: bool, requires_initiation?: bool, requires_payment_proof?: bool, client_config?: array<string, mixed>}>
     */
    public function methodsFor(User $customer, OrderFulfilmentMethod|string|null $fulfilment): array
    {
        $method = $fulfilment instanceof OrderFulfilmentMethod
            ? $fulfilment
            : OrderFulfilmentMethod::tryFrom((string) $fulfilment);

        if ($method === null) {
            return [];
        }

        return $this->catalog->availableMethods($customer, $method);
    }

    /**
     * @return array<string, list<array{key: string, label: string, subtitle: string}>>
     */
    public function methodsByFulfilment(User $customer): array
    {
        return $this->catalog->availableMethodsByFulfilment($customer);
    }

    public function isAllowed(User $customer, OrderFulfilmentMethod|string|null $fulfilment, PaymentMethod|string|null $paymentMethod): bool
    {
        $method = $fulfilment instanceof OrderFulfilmentMethod
            ? $fulfilment
            : OrderFulfilmentMethod::tryFrom((string) $fulfilment);
        $payment = $paymentMethod instanceof PaymentMethod
            ? $paymentMethod
            : PaymentMethod::tryFromApiKey(is_string($paymentMethod) ? $paymentMethod : null);

        if ($method === null || $payment === null) {
            return false;
        }

        return $this->catalog->isAvailable($customer, $method, $payment);
    }

    public function cashAllowed(User $customer, OrderFulfilmentMethod $fulfilment): bool
    {
        return $this->catalog->isAvailable($customer, $fulfilment, PaymentMethod::Cash);
    }

    public function assertAllowed(User $customer, OrderFulfilmentMethod|string|null $fulfilment, PaymentMethod|string|null $paymentMethod): PaymentMethod
    {
        $payment = $paymentMethod instanceof PaymentMethod
            ? $paymentMethod
            : PaymentMethod::tryFromApiKey(is_string($paymentMethod) ? $paymentMethod : null);

        if ($payment === null || ! $this->isAllowed($customer, $fulfilment, $payment)) {
            throw ValidationException::withMessages([
                'payment_method' => 'The selected payment method is not available for this order.',
            ]);
        }

        return $payment;
    }
}
