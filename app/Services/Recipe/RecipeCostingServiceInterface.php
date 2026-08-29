<?php

namespace App\Services\Recipe;

use App\Models\Recipe;

interface RecipeCostingServiceInterface
{
    public function summarize(Recipe $recipe): array;
}
