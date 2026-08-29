<?php

namespace App\Services\Ingredient;

use App\Models\Ingredient;
use App\Transfers\Ingredient\IngredientTransferInterface;

interface IngredientServiceInterface
{
    public function store(IngredientTransferInterface $data): Ingredient;

    public function update(Ingredient $ingredient, IngredientTransferInterface $data): Ingredient;

    public function delete(Ingredient $ingredient): void;
}
