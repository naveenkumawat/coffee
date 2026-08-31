<?php

namespace App\Parsers\Checkout;

use App\Transfers\Checkout\CheckoutTransferInterface;

class CheckoutParser implements CheckoutParserInterface
{
    public function __construct(
        protected CheckoutTransferInterface $transfer,
    ) {}

    public function getTransferFromArrayData(array $checkoutData): CheckoutTransferInterface
    {
        $transfer = clone $this->transfer;
        $transfer->setCheckoutToken(filled($checkoutData['checkout_token'] ?? null) ? trim((string) $checkoutData['checkout_token']) : null);
        $transfer->setCustomerName(filled($checkoutData['customer_name'] ?? null) ? trim((string) $checkoutData['customer_name']) : null);
        $transfer->setCustomerEmail(filled($checkoutData['customer_email'] ?? null) ? trim((string) $checkoutData['customer_email']) : null);
        $transfer->setCustomerPhone(filled($checkoutData['customer_phone'] ?? null) ? trim((string) $checkoutData['customer_phone']) : null);
        $transfer->setPickupName(filled($checkoutData['pickup_name'] ?? null) ? trim((string) $checkoutData['pickup_name']) : null);
        $transfer->setPickupPhone(filled($checkoutData['pickup_phone'] ?? null) ? trim((string) $checkoutData['pickup_phone']) : null);
        $transfer->setCustomerNotes(filled($checkoutData['customer_notes'] ?? null) ? trim((string) $checkoutData['customer_notes']) : null);
        $transfer->setPickupNotes(filled($checkoutData['pickup_notes'] ?? null) ? trim((string) $checkoutData['pickup_notes']) : null);
        $transfer->setFulfilmentMethod(filled($checkoutData['fulfilment_method'] ?? null) ? trim((string) $checkoutData['fulfilment_method']) : null);
        $transfer->setDeliveryAddress(filled($checkoutData['delivery_address'] ?? null) ? trim((string) $checkoutData['delivery_address']) : null);
        $transfer->setDeliveryPhone(filled($checkoutData['delivery_phone'] ?? null) ? trim((string) $checkoutData['delivery_phone']) : null);
        $transfer->setDeliveryContactName(filled($checkoutData['delivery_contact_name'] ?? null) ? trim((string) $checkoutData['delivery_contact_name']) : null);
        $transfer->setDeliveryNotes(filled($checkoutData['delivery_notes'] ?? null) ? trim((string) $checkoutData['delivery_notes']) : null);
        $transfer->setCafeTableId(
            filled($checkoutData['cafe_table_id'] ?? null)
                ? (int) $checkoutData['cafe_table_id']
                : null,
        );

        return $transfer;
    }
}
