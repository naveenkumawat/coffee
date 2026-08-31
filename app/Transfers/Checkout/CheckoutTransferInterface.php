<?php

namespace App\Transfers\Checkout;

interface CheckoutTransferInterface
{
    public function getCheckoutToken(): ?string;

    public function setCheckoutToken(?string $checkoutToken): void;

    public function getCustomerName(): ?string;

    public function setCustomerName(?string $customerName): void;

    public function getCustomerEmail(): ?string;

    public function setCustomerEmail(?string $customerEmail): void;

    public function getCustomerPhone(): ?string;

    public function setCustomerPhone(?string $customerPhone): void;

    public function getPickupName(): ?string;

    public function setPickupName(?string $pickupName): void;

    public function getPickupPhone(): ?string;

    public function setPickupPhone(?string $pickupPhone): void;

    public function getCustomerNotes(): ?string;

    public function setCustomerNotes(?string $customerNotes): void;

    public function getPickupNotes(): ?string;

    public function setPickupNotes(?string $pickupNotes): void;

    public function getFulfilmentMethod(): ?string;

    public function setFulfilmentMethod(?string $fulfilmentMethod): void;

    public function getDeliveryAddress(): ?string;

    public function setDeliveryAddress(?string $deliveryAddress): void;

    public function getDeliveryPhone(): ?string;

    public function setDeliveryPhone(?string $deliveryPhone): void;

    public function getDeliveryContactName(): ?string;

    public function setDeliveryContactName(?string $deliveryContactName): void;

    public function getDeliveryNotes(): ?string;

    public function setDeliveryNotes(?string $deliveryNotes): void;

    public function getCafeTableId(): ?int;

    public function setCafeTableId(?int $cafeTableId): void;

    public function getPaymentMethod(): ?string;

    public function setPaymentMethod(?string $paymentMethod): void;

    public function toArray(): array;
}
