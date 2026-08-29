<?php

namespace App\Services\Recipe;

use App\Models\Recipe;
use App\Transfers\Recipe\RecipeTransferInterface;

interface RecipeServiceInterface
{
    public function store(RecipeTransferInterface $data): Recipe;

    public function update(Recipe $recipe, RecipeTransferInterface $data): Recipe;

    public function delete(Recipe $recipe): void;
}
