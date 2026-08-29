<?php

namespace App\Transfers\Order;

use App\Transfers\AbstractTransfer;

class OrderFilterTransfer extends AbstractTransfer implements OrderFilterTransferInterface
{
    protected ?string $search = null;

    protected ?string $status = null;

    protected ?int $customerId = null;

    protected ?int $assignedBaristaId = null;

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function setSearch(?string $search): void
    {
        $this->search = $search;
    }

    public function hasSearch(): bool
    {
        return filled($this->search);
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function setCustomerId(?int $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getAssignedBaristaId(): ?int
    {
        return $this->assignedBaristaId;
    }

    public function setAssignedBaristaId(?int $assignedBaristaId): void
    {
        $this->assignedBaristaId = $assignedBaristaId;
    }
}
