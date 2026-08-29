<?php

namespace App\Transfers\Inventory;

class InventoryOverviewFilterTransfer implements InventoryOverviewFilterTransferInterface
{
    protected ?string $search = null;

    protected ?int $ingredientCategoryId = null;

    protected ?int $ingredientBrandId = null;

    protected ?string $measurementUnit = null;

    protected ?string $stockStatus = null;

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

    public function getIngredientCategoryId(): ?int
    {
        return $this->ingredientCategoryId;
    }

    public function setIngredientCategoryId(?int $ingredientCategoryId): void
    {
        $this->ingredientCategoryId = $ingredientCategoryId;
    }

    public function getIngredientBrandId(): ?int
    {
        return $this->ingredientBrandId;
    }

    public function setIngredientBrandId(?int $ingredientBrandId): void
    {
        $this->ingredientBrandId = $ingredientBrandId;
    }

    public function getMeasurementUnit(): ?string
    {
        return $this->measurementUnit;
    }

    public function setMeasurementUnit(?string $measurementUnit): void
    {
        $this->measurementUnit = $measurementUnit;
    }

    public function getStockStatus(): ?string
    {
        return $this->stockStatus;
    }

    public function setStockStatus(?string $stockStatus): void
    {
        $this->stockStatus = $stockStatus;
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'ingredient_category_id' => $this->ingredientCategoryId,
            'ingredient_brand_id' => $this->ingredientBrandId,
            'measurement_unit' => $this->measurementUnit,
            'stock_status' => $this->stockStatus,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
