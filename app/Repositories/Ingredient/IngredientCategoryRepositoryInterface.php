<?php

namespace App\Repositories\Ingredient;

use App\Models\IngredientCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface IngredientCategoryRepositoryInterface
{
    public function paginateForAdmin(int $perPage = 12): LengthAwarePaginator;

    public function activeOptions(): array;

    public function allOptions(): array;

    public function create(array $attributes): IngredientCategory;

    public function update(IngredientCategory $ingredientCategory, array $attributes): IngredientCategory;

    public function delete(IngredientCategory $ingredientCategory): void;

    public function hasIngredients(IngredientCategory $ingredientCategory): bool;

    public function activeCategories(): Collection;

    public function findAvailableSlug(string $name, ?int $ignoreId = null): string;
}
