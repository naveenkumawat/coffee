<?php

namespace App\Transfers\Recipe;

interface RecipeFilterTransferInterface
{
    public function getSearch(): ?string;

    public function setSearch(?string $search): void;

    public function hasSearch(): bool;

    public function getProductCategoryId(): ?int;

    public function setProductCategoryId(?int $productCategoryId): void;

    public function getProductId(): ?int;

    public function setProductId(?int $productId): void;

    public function getIngredientId(): ?int;

    public function setIngredientId(?int $ingredientId): void;

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function toArray(): array;
}
