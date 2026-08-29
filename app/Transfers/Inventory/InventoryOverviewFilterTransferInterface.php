<?php

namespace App\Transfers\Inventory;

interface InventoryOverviewFilterTransferInterface
{
    public function getSearch(): ?string;

    public function setSearch(?string $search): void;

    public function hasSearch(): bool;

    public function getIngredientCategoryId(): ?int;

    public function setIngredientCategoryId(?int $ingredientCategoryId): void;

    public function getIngredientBrandId(): ?int;

    public function setIngredientBrandId(?int $ingredientBrandId): void;

    public function getMeasurementUnit(): ?string;

    public function setMeasurementUnit(?string $measurementUnit): void;

    public function getStockStatus(): ?string;

    public function setStockStatus(?string $stockStatus): void;

    public function toArray(): array;
}
