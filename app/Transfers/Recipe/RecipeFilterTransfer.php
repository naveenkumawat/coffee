<?php

namespace App\Transfers\Recipe;

class RecipeFilterTransfer implements RecipeFilterTransferInterface
{
    protected ?string $search = null;

    protected ?int $productCategoryId = null;

    protected ?int $productId = null;

    protected ?int $ingredientId = null;

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

    public function getProductCategoryId(): ?int
    {
        return $this->productCategoryId;
    }

    public function setProductCategoryId(?int $productCategoryId): void
    {
        $this->productCategoryId = $productCategoryId;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): void
    {
        $this->productId = $productId;
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

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'product_category_id' => $this->productCategoryId,
            'product_id' => $this->productId,
            'ingredient_id' => $this->ingredientId,
            'status' => $this->status,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
