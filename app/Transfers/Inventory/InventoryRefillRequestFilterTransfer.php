<?php

namespace App\Transfers\Inventory;

class InventoryRefillRequestFilterTransfer implements InventoryRefillRequestFilterTransferInterface
{
    protected ?string $search = null;

    protected ?int $ingredientId = null;

    protected ?string $status = null;

    protected ?int $requestedBy = null;

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

    public function getIngredientId(): ?int
    {
        return $this->ingredientId;
    }

    public function setIngredientId(?int $ingredientId): void
    {
        $this->ingredientId = $ingredientId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getRequestedBy(): ?int
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?int $requestedBy): void
    {
        $this->requestedBy = $requestedBy;
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'ingredient_id' => $this->ingredientId,
            'status' => $this->status,
            'requested_by' => $this->requestedBy,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
