<?php

namespace App\Parsers\Ingredient;

use App\Models\Ingredient;
use App\Transfers\Ingredient\IngredientFilterTransferInterface;
use App\Transfers\Ingredient\IngredientTransferInterface;

interface IngredientParserInterface
{
    public function getTransferFromModelEntity(Ingredient $ingredient): IngredientTransferInterface;

    public function getTransferFromArrayData(array $ingredientData): IngredientTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): IngredientFilterTransferInterface;
}
