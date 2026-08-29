<?php

namespace App\Repositories\Ingredient;

use App\Models\IngredientBrand;
use App\Transfers\Ingredient\IngredientBrandFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface IngredientBrandRepositoryInterface
{
    public function paginateForAdmin(IngredientBrandFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function activeOptions(): array;

    public function allOptions(): array;

    public function create(array $attributes): IngredientBrand;

    public function update(IngredientBrand $ingredientBrand, array $attributes): IngredientBrand;

    public function delete(IngredientBrand $ingredientBrand): void;

    public function hasIngredients(IngredientBrand $ingredientBrand): bool;

    public function findAvailableSlug(string $name, ?int $ignoreId = null): string;
}
