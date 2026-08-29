<?php

namespace App\Transfers\Order;

interface OrderFilterTransferInterface
{
    public function getSearch(): ?string;

    public function setSearch(?string $search): void;

    public function hasSearch(): bool;

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function getCustomerId(): ?int;

    public function setCustomerId(?int $customerId): void;

    public function getAssignedBaristaId(): ?int;

    public function setAssignedBaristaId(?int $assignedBaristaId): void;
}
