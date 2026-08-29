<?php

namespace App\Transfers\Order;

interface OrderStatusTransitionTransferInterface
{
    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function getNotes(): ?string;

    public function setNotes(?string $notes): void;

    public function toArray(): array;
}
