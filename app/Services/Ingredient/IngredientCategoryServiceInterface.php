<?php

namespace App\Services\Ingredient;

use App\Models\IngredientCategory;
use App\Transfers\Ingredient\IngredientCategoryTransferInterface;

interface IngredientCategoryServiceInterface
{
    public function store(IngredientCategoryTransferInterface $data): IngredientCategory;

    public function update(IngredientCategory $ingredientCategory, IngredientCategoryTransferInterface $data): IngredientCategory;

    public function delete(IngredientCategory $ingredientCategory): void;
}
