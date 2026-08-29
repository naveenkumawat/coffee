<?php

namespace App\Repositories\Recipe;

use App\Models\Ingredient;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Transfers\Recipe\RecipeFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RecipeRepositoryInterface
{
    public function paginateForAdmin(RecipeFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function paginateForBarista(RecipeFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function productOptions(): array;

    public function activeIngredientOptions(): array;

    public function activeVariantOptions(): array;

    public function findVariant(int $variantId): ?ProductVariant;

    public function findActiveIngredient(int $ingredientId): ?Ingredient;

    public function create(array $attributes): Recipe;

    public function update(Recipe $recipe, array $attributes): Recipe;

    public function delete(Recipe $recipe): void;

    public function replaceLines(Recipe $recipe, array $lines): Recipe;

    public function existsForVariant(int $productVariantId, ?int $ignoreRecipeId = null): bool;
}
