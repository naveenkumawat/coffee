<?php

namespace App\Services\WhatsApp;

use App\Enums\CustomerNotificationType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\CustomerAppUrl;
use App\Support\CustomerEmailBrand;
use App\Support\PhoneNumber;

class WhatsAppTemplatePayloadFactory
{
    /**
     * Build a Meta template message for an eligible order notification, or null when skipped.
     */
    public function make(
        CustomerNotificationType $type,
        Order $order,
        string $destination,
        ?string $customerFacingReason = null,
    ): ?WhatsAppTemplateMessage {
        $templateKey = $this->templateKey($type, $order);

        if ($templateKey === null) {
            return null;
        }

        $templateName = trim((string) config('services.whatsapp.templates.'.$templateKey));

        if ($templateName === '') {
            return null;
        }

        $language = trim((string) config('services.whatsapp.language', 'en')) ?: 'en';
        $brand = CustomerEmailBrand::snapshot();
        $parameters = $this->bodyParameters($templateKey, $order, $brand, $customerFacingReason);

        return new WhatsAppTemplateMessage(
            to: $destination,
            templateName: $templateName,
            language: $language,
            bodyParameters: $parameters,
            templateKey: $templateKey,
            // Approved Meta CTA buttons can be attached later without inventing unsupported shapes.
            extraComponents: null,
        );
    }

    public function templateKey(CustomerNotificationType $type, Order $order): ?string
    {
        return match ($type) {
            CustomerNotificationType::OrderPlaced => 'order_placed',
            CustomerNotificationType::PaymentProofReceived => 'payment_proof_received',
            CustomerNotificationType::PaymentConfirmed => 'payment_confirmed',
            CustomerNotificationType::PaymentProofRejected => 'payment_proof_rejected',
            CustomerNotificationType::OrderAccepted => 'order_accepted',
            CustomerNotificationType::OrderPreparing => (bool) config('services.whatsapp.send_preparing', false)
                ? 'order_preparing'
                : null,
            CustomerNotificationType::OrderReady => $order->fulfilment_method === OrderFulfilmentMethod::Delivery
                ? 'order_ready_delivery'
                : 'order_ready_pickup',
            CustomerNotificationType::OrderCompleted => 'order_completed',
            CustomerNotificationType::OrderCancelled,
            CustomerNotificationType::OrderRejected => 'order_cancelled',
            default => null,
        };
    }

    public function resolveDestination(Order $order): ?string
    {
        $candidates = [
            $order->customer_phone,
            $order->pickup_phone,
            $order->delivery_phone,
            $order->customer?->phone,
        ];

        foreach ($candidates as $candidate) {
            $destination = PhoneNumber::toWhatsappDestination(is_string($candidate) ? $candidate : null);

            if ($destination !== null) {
                return $destination;
            }
        }

        return null;
    }

    /**
     * @param  array{business_name: string, address: string|null, delivery_disclaimer: string|null}  $brand
     * @return list<string>
     */
    protected function bodyParameters(
        string $templateKey,
        Order $order,
        array $brand,
        ?string $customerFacingReason,
    ): array {
        $name = CustomerEmailBrand::firstName($order->customer_name) ?: 'there';
        $number = (string) $order->order_number;
        $total = number_format((float) $order->total_amount, 2, '.', '');
        $business = (string) $brand['business_name'];
        $fulfilment = $order->fulfilment_method === OrderFulfilmentMethod::Delivery
            ? 'Delivery'
            : 'Takeaway';
        $reason = $this->safeReason($customerFacingReason);

        return match ($templateKey) {
            'order_placed' => [$name, $number, $total, $fulfilment, $business],
            'payment_proof_received' => [$name, $number, $business],
            'payment_confirmed' => [$name, $number, $business],
            'payment_proof_rejected' => [$name, $number, $reason ?: 'Please upload a clearer payment screenshot.', $business],
            'order_accepted' => [$name, $number, $business],
            'order_preparing' => [$name, $number, $business],
            'order_ready_pickup' => [
                $name,
                $number,
                filled($brand['address']) ? (string) $brand['address'] : 'the café',
                $business,
            ],
            'order_ready_delivery' => [
                $name,
                $number,
                $this->deliveryAddressSummary($order) ?: 'your delivery address',
                $business,
            ],
            'order_completed' => [$name, $number, CustomerAppUrl::order($order->getKey()), $business],
            'order_cancelled' => [
                $name,
                $number,
                $order->status === OrderStatus::Rejected ? 'Rejected' : 'Cancelled',
                $reason ?: 'Please contact the café if you have questions.',
                $business,
            ],
            default => [$name, $number, $business],
        };
    }

    protected function deliveryAddressSummary(Order $order): ?string
    {
        $address = trim((string) $order->delivery_address);

        if ($address === '') {
            return null;
        }

        $address = preg_replace('/\s+/', ' ', $address) ?? $address;

        return mb_strlen($address) > 120 ? mb_substr($address, 0, 117).'…' : $address;
    }

    protected function safeReason(?string $reason): ?string
    {
        $reason = trim(strip_tags((string) $reason));

        if ($reason === '') {
            return null;
        }

        return mb_strlen($reason) > 200 ? mb_substr($reason, 0, 197).'…' : $reason;
    }
}
