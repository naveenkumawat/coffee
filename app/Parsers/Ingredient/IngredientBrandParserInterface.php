<?php

namespace App\Parsers\Ingredient;

use App\Models\IngredientBrand;
use App\Transfers\Ingredient\IngredientBrandFilterTransferInterface;
use App\Transfers\Ingredient\IngredientBrandTransferInterface;

interface IngredientBrandParserInterface
{
    public function getTransferFromModelEntity(IngredientBrand $ingredientBrand): IngredientBrandTransferInterface;

    public function getTransferFromArrayData(array $brandData): IngredientBrandTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): IngredientBrandFilterTransferInterface;
}
