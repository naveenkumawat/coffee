<?php

namespace App\Transfers\Order;

interface OrderTransferInterface
{
    public function getCustomerId(): ?int;

    public function setCustomerId(?int $customerId): void;

    public function getCustomerNotes(): ?string;

    public function setCustomerNotes(?string $customerNotes): void;

    public function getItems(): array;

    public function setItems(array $items): void;

    public function toArray(): array;
}
