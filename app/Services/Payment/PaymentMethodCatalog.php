<?php

namespace App\Services\Payment;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\PaymentMethod;
use App\Models\User;
use App\Repositories\WebsiteSetting\WebsiteSettingRepositoryInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;

class PaymentMethodCatalog
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected WebsiteSettingRepositoryInterface $settings,
        protected WebsiteSettingServiceInterface $websiteSettings,
    ) {}

    public function isEnabled(PaymentMethod $method): bool
    {
        $key = $method->apiKey();
        $settingKey = 'payment_'.$key.'_enabled';
        $stored = $this->settings->keyedValues()->get($settingKey);

        if ($stored !== null && $stored !== '') {
            return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('coffee.payments.methods.'.$key.'.enabled', $method === PaymentMethod::Manual || $method === PaymentMethod::Cash);
    }

    public function isConfigured(PaymentMethod $method): bool
    {
        if ($method === PaymentMethod::Cash) {
            return true;
        }

        if ($method === PaymentMethod::Manual) {
            $instructions = $this->websiteSettings->paymentInstructions();

            return filled($instructions['upi_id'] ?? null) || filled($instructions['qr_image_path'] ?? null);
        }

        return $this->gateways->isConfigured($method);
    }

    public function configurationStatus(PaymentMethod $method): string
    {
        if (! $this->isEnabled($method)) {
            return 'disabled';
        }

        return $this->isConfigured($method) ? 'ready' : 'incomplete';
    }

    public function fulfilmentEligible(User $customer, OrderFulfilmentMethod $fulfilment, PaymentMethod $method): bool
    {
        return match ($method) {
            PaymentMethod::Cash => match ($fulfilment) {
                OrderFulfilmentMethod::DineIn => true,
                OrderFulfilmentMethod::Takeaway => (bool) $customer->cash_takeaway_allowed,
                OrderFulfilmentMethod::Delivery => false,
            },
            PaymentMethod::Manual,
            PaymentMethod::Razorpay,
            PaymentMethod::PayU,
            PaymentMethod::Paytm,
            PaymentMethod::PhonePe => true,
        };
    }

    public function isAvailable(User $customer, OrderFulfilmentMethod $fulfilment, PaymentMethod $method): bool
    {
        return $this->isEnabled($method)
            && $this->isConfigured($method)
            && $this->fulfilmentEligible($customer, $fulfilment, $method);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function availableMethods(User $customer, OrderFulfilmentMethod $fulfilment): array
    {
        $methods = [];

        foreach (PaymentMethod::cases() as $method) {
            if (! $this->isAvailable($customer, $fulfilment, $method)) {
                continue;
            }

            $methods[] = $this->customerPayload($method, $fulfilment, available: true);
        }

        return $methods;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function availableMethodsByFulfilment(User $customer): array
    {
        $result = [];

        foreach (OrderFulfilmentMethod::cases() as $fulfilment) {
            $result[$fulfilment->value] = $this->availableMethods($customer, $fulfilment);
        }

        return $result;
    }

    /**
     * Admin diagnostics for all methods.
     *
     * @return list<array<string, mixed>>
     */
    public function adminDiagnostics(): array
    {
        $rows = [];

        foreach (PaymentMethod::cases() as $method) {
            $rows[] = [
                'code' => $method->apiKey(),
                'name' => $method->label(),
                'type' => $method->type(),
                'enabled' => $this->isEnabled($method),
                'configured' => $this->isConfigured($method),
                'configuration_status' => $this->configurationStatus($method),
                'mode' => $method->isOnline() ? $this->gateways->gateway($method)->mode() : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function customerPayload(PaymentMethod $method, ?OrderFulfilmentMethod $fulfilment = null, bool $available = true): array
    {
        $payload = [
            'code' => $method->apiKey(),
            'key' => $method->apiKey(),
            'name' => $method->customerLabel($fulfilment),
            'label' => $method->customerLabel($fulfilment),
            'subtitle' => $method->customerSubtitle($fulfilment),
            'type' => $method->type(),
            'available' => $available,
            'requires_initiation' => $method->requiresGatewayInitiation(),
            'requires_payment_proof' => $method->requiresPaymentProof(),
        ];

        if ($method->isOnline() && $available) {
            $payload['client_config'] = $this->gateways->gateway($method)->clientConfig();
        }

        return $payload;
    }
}
