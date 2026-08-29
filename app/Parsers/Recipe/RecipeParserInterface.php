<?php

namespace App\Parsers\Recipe;

use App\Models\Recipe;
use App\Transfers\Recipe\RecipeFilterTransferInterface;
use App\Transfers\Recipe\RecipeTransferInterface;

interface RecipeParserInterface
{
    public function getTransferFromModelEntity(Recipe $recipe): RecipeTransferInterface;

    public function getTransferFromArrayData(array $recipeData): RecipeTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): RecipeFilterTransferInterface;
}
