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

    public function getDeliveryAddressId(): ?int;

    public function setDeliveryAddressId(?int $deliveryAddressId): void;

    public function getAddressLine1(): ?string;

    public function setAddressLine1(?string $addressLine1): void;

    public function getAddressLine2(): ?string;

    public function setAddressLine2(?string $addressLine2): void;

    public function getLandmark(): ?string;

    public function setLandmark(?string $landmark): void;

    public function getCity(): ?string;

    public function setCity(?string $city): void;

    public function getState(): ?string;

    public function setState(?string $state): void;

    public function getPostalCode(): ?string;

    public function setPostalCode(?string $postalCode): void;

    public function getAddressLabel(): ?string;

    public function setAddressLabel(?string $addressLabel): void;

    public function getSaveDeliveryAddress(): bool;

    public function setSaveDeliveryAddress(bool $saveDeliveryAddress): void;

    public function getMakeDefaultAddress(): bool;

    public function setMakeDefaultAddress(bool $makeDefaultAddress): void;

    public function getCafeTableId(): ?int;

    public function setCafeTableId(?int $cafeTableId): void;

    public function getPaymentMethod(): ?string;

    public function setPaymentMethod(?string $paymentMethod): void;

    public function toArray(): array;
}
