<?php

namespace App\Transfers\Ingredient;

interface IngredientFilterTransferInterface
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

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function toArray(): array;
}
