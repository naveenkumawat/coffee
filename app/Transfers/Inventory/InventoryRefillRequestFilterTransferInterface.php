<?php

namespace App\Transfers\Inventory;

interface InventoryRefillRequestFilterTransferInterface
{
    public function getSearch(): ?string;

    public function setSearch(?string $search): void;

    public function hasSearch(): bool;

    public function getIngredientId(): ?int;

    public function setIngredientId(?int $ingredientId): void;

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function getRequestedBy(): ?int;

    public function setRequestedBy(?int $requestedBy): void;

    public function toArray(): array;
}
