<?php

namespace App\Repositories\Ingredient;

use App\Models\Ingredient;
use App\Models\IngredientBrand;
use App\Models\IngredientCategory;
use App\Transfers\Ingredient\IngredientFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface IngredientRepositoryInterface
{
    public function paginateForAdmin(IngredientFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function paginateForCategory(IngredientCategory $ingredientCategory, int $perPage = 12): LengthAwarePaginator;

    public function paginateForBrand(IngredientBrand $ingredientBrand, int $perPage = 12): LengthAwarePaginator;

    public function create(array $attributes): Ingredient;

    public function update(Ingredient $ingredient, array $attributes): Ingredient;

    public function delete(Ingredient $ingredient): void;
}
