<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Services\Payment\Gateways\PaymentGatewayContract;
use App\Services\Payment\Gateways\PaytmGateway;
use App\Services\Payment\Gateways\PayUGateway;
use App\Services\Payment\Gateways\PhonePeGateway;
use App\Services\Payment\Gateways\RazorpayGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGatewayContract>
     */
    protected array $resolved = [];

    public function gateway(PaymentMethod|string $method): PaymentGatewayContract
    {
        $payment = $method instanceof PaymentMethod
            ? $method
            : PaymentMethod::tryFromApiKey((string) $method);

        if ($payment === null || ! $payment->isOnline()) {
            throw new InvalidArgumentException('Online payment gateway is required.');
        }

        $code = $payment->apiKey();

        if (isset($this->resolved[$code])) {
            return $this->resolved[$code];
        }

        $config = (array) config('coffee.payments.gateways.'.$code, []);

        $gateway = match ($payment) {
            PaymentMethod::Razorpay => new RazorpayGateway($config),
            PaymentMethod::PayU => new PayUGateway($config),
            PaymentMethod::Paytm => new PaytmGateway($config),
            PaymentMethod::PhonePe => new PhonePeGateway($config),
            default => throw new InvalidArgumentException('Unsupported gateway.'),
        };

        return $this->resolved[$code] = $gateway;
    }

    public function isConfigured(PaymentMethod|string $method): bool
    {
        $payment = $method instanceof PaymentMethod
            ? $method
            : PaymentMethod::tryFromApiKey((string) $method);

        if ($payment === null) {
            return false;
        }

        if (! $payment->isOnline()) {
            return $this->manualConfigured($payment);
        }

        return $this->gateway($payment)->isConfigured();
    }

    public function manualConfigured(PaymentMethod $method): bool
    {
        if ($method === PaymentMethod::Cash) {
            return true;
        }

        if ($method === PaymentMethod::Manual) {
            return filled(config('coffee.payments.upi_id'))
                || filled(config('coffee.payments.qr_image_path'));
        }

        return false;
    }

    /**
     * @return list<PaymentGatewayContract>
     */
    public function onlineGateways(): array
    {
        return array_map(
            fn (PaymentMethod $method): PaymentGatewayContract => $this->gateway($method),
            PaymentMethod::onlineCases(),
        );
    }
}
