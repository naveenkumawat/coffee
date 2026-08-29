<?php

namespace App\Transfers\Product;

class ProductCategoryFilterTransfer implements ProductCategoryFilterTransferInterface
{
    protected ?string $search = null;

    protected ?string $status = null;

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

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
