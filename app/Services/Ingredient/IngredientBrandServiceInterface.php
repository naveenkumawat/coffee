<?php

namespace App\Services\Ingredient;

use App\Models\IngredientBrand;
use App\Transfers\Ingredient\IngredientBrandTransferInterface;

interface IngredientBrandServiceInterface
{
    public function store(IngredientBrandTransferInterface $data): IngredientBrand;

    public function update(IngredientBrand $ingredientBrand, IngredientBrandTransferInterface $data): IngredientBrand;

    public function delete(IngredientBrand $ingredientBrand): void;
}
