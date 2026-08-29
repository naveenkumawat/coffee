<?php

namespace App\Transfers\Product;

class ProductFlavourFilterTransfer implements ProductFlavourFilterTransferInterface
{
    protected ?string $search = null;

    protected ?string $status = null;

    protected ?int $productCategoryId = null;

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

    public function getProductCategoryId(): ?int
    {
        return $this->productCategoryId;
    }

    public function setProductCategoryId(?int $productCategoryId): void
    {
        $this->productCategoryId = $productCategoryId;
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status,
            'product_category_id' => $this->productCategoryId,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
