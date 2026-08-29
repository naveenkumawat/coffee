<?php

namespace App\Transfers\Ingredient;

class IngredientFilterTransfer implements IngredientFilterTransferInterface
{
    protected ?string $search = null;

    protected ?int $ingredientCategoryId = null;

    protected ?int $ingredientBrandId = null;

    protected ?string $measurementUnit = null;

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

    public function getIngredientCategoryId(): ?int
    {
        return $this->ingredientCategoryId;
    }

    public function setIngredientCategoryId(?int $ingredientCategoryId): void
    {
        $this->ingredientCategoryId = $ingredientCategoryId;
    }

    public function getMeasurementUnit(): ?string
    {
        return $this->measurementUnit;
    }

    public function setMeasurementUnit(?string $measurementUnit): void
    {
        $this->measurementUnit = $measurementUnit;
    }

    public function getIngredientBrandId(): ?int
    {
        return $this->ingredientBrandId;
    }

    public function setIngredientBrandId(?int $ingredientBrandId): void
    {
        $this->ingredientBrandId = $ingredientBrandId;
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
            'ingredient_category_id' => $this->ingredientCategoryId,
            'ingredient_brand_id' => $this->ingredientBrandId,
            'measurement_unit' => $this->measurementUnit,
            'status' => $this->status,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
