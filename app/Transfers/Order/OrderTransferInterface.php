<?php

namespace App\Transfers\Order;

interface OrderTransferInterface
{
    public function getCustomerId(): ?int;

    public function setCustomerId(?int $customerId): void;

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

    public function getItems(): array;

    public function setItems(array $items): void;

    public function toArray(): array;
}
