<?php

namespace App\Parsers\Ingredient;

use App\Models\IngredientCategory;
use App\Transfers\Ingredient\IngredientCategoryTransferInterface;

interface IngredientCategoryParserInterface
{
    public function getTransferFromModelEntity(IngredientCategory $ingredientCategory): IngredientCategoryTransferInterface;

    public function getTransferFromArrayData(array $categoryData): IngredientCategoryTransferInterface;
}
