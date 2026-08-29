<?php

namespace App\Transfers\Ingredient;

interface IngredientBrandFilterTransferInterface
{
    public function getSearch(): ?string;

    public function setSearch(?string $search): void;

    public function hasSearch(): bool;

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function toArray(): array;
}
