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
        $transfer->setDeliveryAddressId(
            filled($checkoutData['delivery_address_id'] ?? null)
                ? (int) $checkoutData['delivery_address_id']
                : null,
        );
        $transfer->setAddressLabel(filled($checkoutData['address_label'] ?? null) ? trim((string) $checkoutData['address_label']) : null);
        $transfer->setAddressLine1(filled($checkoutData['address_line_1'] ?? null) ? trim((string) $checkoutData['address_line_1']) : null);
        $transfer->setAddressLine2(filled($checkoutData['address_line_2'] ?? null) ? trim((string) $checkoutData['address_line_2']) : null);
        $transfer->setLandmark(filled($checkoutData['landmark'] ?? null) ? trim((string) $checkoutData['landmark']) : null);
        $transfer->setCity(filled($checkoutData['city'] ?? null) ? trim((string) $checkoutData['city']) : null);
        $transfer->setState(filled($checkoutData['state'] ?? null) ? trim((string) $checkoutData['state']) : null);
        $transfer->setPostalCode(filled($checkoutData['postal_code'] ?? null) ? trim((string) $checkoutData['postal_code']) : null);
        $transfer->setSaveDeliveryAddress((bool) ($checkoutData['save_delivery_address'] ?? false));
        $transfer->setMakeDefaultAddress((bool) ($checkoutData['make_default_address'] ?? false));
        $transfer->setCafeTableId(
            filled($checkoutData['cafe_table_id'] ?? null)
                ? (int) $checkoutData['cafe_table_id']
                : null,
        );
        $transfer->setPaymentMethod(
            filled($checkoutData['payment_method'] ?? null)
                ? trim((string) $checkoutData['payment_method'])
                : null,
        );

        return $transfer;
    }
}
