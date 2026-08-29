<?php

namespace App\Transfers\Order;

use App\Transfers\AbstractTransfer;

class OrderStatusTransitionTransfer extends AbstractTransfer implements OrderStatusTransitionTransferInterface
{
    protected ?string $status = null;

    protected ?string $notes = null;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}
