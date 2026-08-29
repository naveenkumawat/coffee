<?php

namespace App\Transfers\Order;

use App\Transfers\AbstractTransfer;

class OrderTransfer extends AbstractTransfer implements OrderTransferInterface
{
    protected ?int $customerId = null;

    protected ?string $customerNotes = null;

    protected array $items = [];

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function setCustomerId(?int $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomerNotes(): ?string
    {
        return $this->customerNotes;
    }

    public function setCustomerNotes(?string $customerNotes): void
    {
        $this->customerNotes = $customerNotes;
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
            'customer_notes' => $this->customerNotes,
            'items' => $this->items,
        ];
    }
}
