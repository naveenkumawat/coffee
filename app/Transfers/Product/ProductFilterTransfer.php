<?php

namespace App\Transfers\Product;

class ProductFilterTransfer implements ProductFilterTransferInterface
{
    protected ?string $search = null;

    protected ?int $productCategoryId = null;

    protected ?int $productFlavourId = null;

    protected ?string $status = null;

    protected ?string $availability = null;

    protected ?string $featured = null;

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

    public function getProductCategoryId(): ?int
    {
        return $this->productCategoryId;
    }

    public function setProductCategoryId(?int $productCategoryId): void
    {
        $this->productCategoryId = $productCategoryId;
    }

    public function getProductFlavourId(): ?int
    {
        return $this->productFlavourId;
    }

    public function setProductFlavourId(?int $productFlavourId): void
    {
        $this->productFlavourId = $productFlavourId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getAvailability(): ?string
    {
        return $this->availability;
    }

    public function setAvailability(?string $availability): void
    {
        $this->availability = $availability;
    }

    public function getFeatured(): ?string
    {
        return $this->featured;
    }

    public function setFeatured(?string $featured): void
    {
        $this->featured = $featured;
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'product_category_id' => $this->productCategoryId,
            'product_flavour_id' => $this->productFlavourId,
            'status' => $this->status,
            'availability' => $this->availability,
            'featured' => $this->featured,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
