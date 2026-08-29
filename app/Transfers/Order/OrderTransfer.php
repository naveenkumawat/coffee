<?php

namespace App\Transfers\Order;

use App\Transfers\AbstractTransfer;

class OrderTransfer extends AbstractTransfer implements OrderTransferInterface
{
    protected ?int $customerId = null;

    protected ?string $checkoutToken = null;

    protected ?string $customerName = null;

    protected ?string $customerEmail = null;

    protected ?string $customerPhone = null;

    protected ?string $pickupName = null;

    protected ?string $pickupPhone = null;

    protected ?string $customerNotes = null;

    protected ?string $pickupNotes = null;

    protected array $items = [];

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function setCustomerId(?int $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCheckoutToken(): ?string
    {
        return $this->checkoutToken;
    }

    public function setCheckoutToken(?string $checkoutToken): void
    {
        $this->checkoutToken = $checkoutToken;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(?string $customerName): void
    {
        $this->customerName = $customerName;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): void
    {
        $this->customerEmail = $customerEmail;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(?string $customerPhone): void
    {
        $this->customerPhone = $customerPhone;
    }

    public function getPickupName(): ?string
    {
        return $this->pickupName;
    }

    public function setPickupName(?string $pickupName): void
    {
        $this->pickupName = $pickupName;
    }

    public function getPickupPhone(): ?string
    {
        return $this->pickupPhone;
    }

    public function setPickupPhone(?string $pickupPhone): void
    {
        $this->pickupPhone = $pickupPhone;
    }

    public function getCustomerNotes(): ?string
    {
        return $this->customerNotes;
    }

    public function setCustomerNotes(?string $customerNotes): void
    {
        $this->customerNotes = $customerNotes;
    }

    public function getPickupNotes(): ?string
    {
        return $this->pickupNotes;
    }

    public function setPickupNotes(?string $pickupNotes): void
    {
        $this->pickupNotes = $pickupNotes;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function toArray(): array
    {
        return [
            'customer_id' => $this->customerId,
            'checkout_token' => $this->checkoutToken,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'customer_phone' => $this->customerPhone,
            'pickup_name' => $this->pickupName,
            'pickup_phone' => $this->pickupPhone,
            'customer_notes' => $this->customerNotes,
            'pickup_notes' => $this->pickupNotes,
            'items' => $this->items,
        ];
    }
}
