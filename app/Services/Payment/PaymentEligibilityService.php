<?php

namespace App\Services\Payment;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PaymentEligibilityService implements PaymentEligibilityServiceInterface
{
    /**
     * @return list<array{key: string, label: string, subtitle: string}>
     */
    public function methodsFor(User $customer, OrderFulfilmentMethod|string|null $fulfilment): array
    {
        $method = $fulfilment instanceof OrderFulfilmentMethod
            ? $fulfilment
            : OrderFulfilmentMethod::tryFrom((string) $fulfilment);

        if ($method === null) {
            return [];
        }

        $options = [PaymentMethod::Manual];

        if ($this->cashAllowed($customer, $method)) {
            $options[] = PaymentMethod::Cash;
        }

        return array_map(
            fn (PaymentMethod $payment): array => [
                'key' => $payment->apiKey(),
                'label' => $payment->customerLabel($method),
                'subtitle' => $payment->customerSubtitle($method),
            ],
            $options,
        );
    }

    /**
     * @return array<string, list<array{key: string, label: string, subtitle: string}>>
     */
    public function methodsByFulfilment(User $customer): array
    {
        $result = [];

        foreach (OrderFulfilmentMethod::cases() as $fulfilment) {
            $result[$fulfilment->value] = $this->methodsFor($customer, $fulfilment);
        }

        return $result;
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

        foreach ($this->methodsFor($customer, $method) as $option) {
            if ($option['key'] === $payment->apiKey()) {
                return true;
            }
        }

        return false;
    }

    public function cashAllowed(User $customer, OrderFulfilmentMethod $fulfilment): bool
    {
        return match ($fulfilment) {
            OrderFulfilmentMethod::DineIn => true,
            OrderFulfilmentMethod::Takeaway => (bool) $customer->cash_takeaway_allowed,
            OrderFulfilmentMethod::Delivery => false,
        };
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
