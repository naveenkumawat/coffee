<?php

namespace App\Repositories\Recipe;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Repositories\AbstractRepository;
use App\Transfers\Recipe\RecipeFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RecipeRepository extends AbstractRepository implements RecipeRepositoryInterface
{
    public function __construct(
        protected Recipe $model,
        protected ProductVariant $variantModel,
        protected Ingredient $ingredientModel,
        protected Product $productModel,
    ) {}

    public function paginateForAdmin(RecipeFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->orderByDesc('recipes.is_active')
            ->orderBy('products.name')
            ->orderBy('product_variants.sort_order')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForBarista(RecipeFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->where('recipes.is_active', true)
            ->where('products.is_active', true)
            ->where('product_variants.is_active', true)
            ->orderBy('products.name')
            ->orderBy('product_variants.sort_order')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function productOptions(): array
    {
        return $this->productModel->newQuery()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function activeIngredientOptions(): array
    {
        return $this->ingredientModel->newQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function activeVariantOptions(): array
    {
        return $this->variantModel->newQuery()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with('product.category')
            ->get()
            ->mapWithKeys(function (ProductVariant $variant): array {
                return [
                    $variant->getKey() => sprintf(
                        '%s - %s%s',
                        $variant->product?->name ?? 'Product',
                        $variant->name,
                        $variant->product?->category?->name ? sprintf(' (%s)', $variant->product->category->name) : '',
                    ),
                ];
            })
            ->all();
    }

    public function findVariant(int $variantId): ?ProductVariant
    {
        return $this->variantModel->newQuery()
            ->with(['product.category', 'recipe.lines.ingredient'])
            ->find($variantId);
    }

    public function findActiveIngredient(int $ingredientId): ?Ingredient
    {
        return $this->ingredientModel->newQuery()
            ->where('is_active', true)
            ->find($ingredientId);
    }

    public function create(array $attributes): Recipe
    {
        /** @var Recipe $recipe */
        $recipe = $this->persist($this->model->newInstance(), $attributes);

        return $recipe;
    }

    public function update(Recipe $recipe, array $attributes): Recipe
    {
        /** @var Recipe $recipe */
        $recipe = $this->persist($recipe, $attributes);

        return $recipe;
    }

    public function delete(Recipe $recipe): void
    {
        $this->remove($recipe);
    }

    public function replaceLines(Recipe $recipe, array $lines): Recipe
    {
        $existingLines = $recipe->lines()->get()->keyBy('id');
        $keptLineIds = [];

        foreach ($lines as $index => $line) {
            $lineModel = filled($line['id'] ?? null)
                ? $existingLines->get((int) $line['id'])
                : null;

            $attributes = [
                'ingredient_id' => $line['ingredient_id'],
                'quantity' => $line['quantity'],
                'measurement_unit' => $line['measurement_unit'],
                'base_quantity' => $line['base_quantity'],
                'base_measurement_unit' => $line['base_measurement_unit'],
                'sort_order' => $line['sort_order'] ?: ($index + 1),
                'show_to_customer' => (bool) ($line['show_to_customer'] ?? false),
                'customer_label' => filled($line['customer_label'] ?? null)
                    ? trim((string) $line['customer_label'])
                    : null,
            ];

            if ($lineModel && (int) $lineModel->recipe_id === (int) $recipe->getKey()) {
                $lineModel->fill($attributes)->save();
                $keptLineIds[] = (int) $lineModel->getKey();

                continue;
            }

            $createdLine = $recipe->lines()->create($attributes);
            $keptLineIds[] = (int) $createdLine->getKey();
        }

        if ($keptLineIds !== []) {
            $recipe->lines()->whereNotIn('id', $keptLineIds)->delete();
        } else {
            $recipe->lines()->delete();
        }

        return $recipe->fresh(['variant.product.category', 'lines.ingredient']);
    }

    public function existsForVariant(int $productVariantId, ?int $ignoreRecipeId = null): bool
    {
        return $this->model->newQuery()
            ->where('product_variant_id', $productVariantId)
            ->when($ignoreRecipeId, fn ($query) => $query->whereKeyNot($ignoreRecipeId))
            ->exists();
    }

    protected function filteredQuery(RecipeFilterTransferInterface $filters)
    {
        return $this->model->newQuery()
            ->select('recipes.*')
            ->join('product_variants', 'product_variants.id', '=', 'recipes.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->with([
                'variant.product.category',
                'lines.ingredient.brand',
            ])
            ->withCount('lines')
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('products.name', 'like', "%{$search}%")
                        ->orWhere('product_variants.name', 'like', "%{$search}%")
                        ->orWhere('recipes.preparation_notes', 'like', "%{$search}%")
                        ->orWhereHas('lines.ingredient', fn ($ingredientQuery) => $ingredientQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters->getProductCategoryId(), fn ($query) => $query->where('products.product_category_id', $filters->getProductCategoryId()))
            ->when($filters->getProductId(), fn ($query) => $query->where('products.id', $filters->getProductId()))
            ->when($filters->getIngredientId(), fn ($query) => $query->whereHas('lines', fn ($lineQuery) => $lineQuery->where('ingredient_id', $filters->getIngredientId())))
            ->when($filters->getStatus() === 'active', fn ($query) => $query->where('recipes.is_active', true))
            ->when($filters->getStatus() === 'inactive', fn ($query) => $query->where('recipes.is_active', false));
    }
}
