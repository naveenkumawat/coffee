<?php

namespace App\Services\Recipe;

use App\Enums\IngredientUnit;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Repositories\Recipe\RecipeRepositoryInterface;
use App\Transfers\Recipe\RecipeTransferInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecipeService implements RecipeServiceInterface
{
    public function __construct(
        protected RecipeRepositoryInterface $recipes,
    ) {}

    public function store(RecipeTransferInterface $data): Recipe
    {
        return DB::transaction(function () use ($data): Recipe {
            $variant = $this->validateVariant((int) $data->getProductVariantId());

            if ($this->recipes->existsForVariant($variant->getKey())) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'A recipe already exists for this product variant.',
                ]);
            }

            $recipe = $this->recipes->create(array_merge($data->toArray(), [
                'version' => 1,
            ]));

            return $this->recipes->replaceLines($recipe, $this->prepareLines($data->getLines()));
        });
    }

    public function update(Recipe $recipe, RecipeTransferInterface $data): Recipe
    {
        return DB::transaction(function () use ($recipe, $data): Recipe {
            $variant = $this->validateVariant((int) $data->getProductVariantId());

            if ($this->recipes->existsForVariant($variant->getKey(), (int) $recipe->getKey())) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'A recipe already exists for this product variant.',
                ]);
            }

            $recipe = $this->recipes->update($recipe, array_merge($data->toArray(), [
                'version' => $recipe->version,
            ]));

            return $this->recipes->replaceLines($recipe, $this->prepareLines($data->getLines()));
        });
    }

    public function delete(Recipe $recipe): void
    {
        DB::transaction(function () use ($recipe): void {
            $recipe->forceFill(['is_active' => false])->save();
            $this->recipes->delete($recipe);
        });
    }

    protected function validateVariant(int $variantId): ProductVariant
    {
        $variant = $this->recipes->findVariant($variantId);

        if (! $variant || ! $variant->is_active || ! $variant->product || ! $variant->product->is_active) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Only active product variants can be assigned a recipe.',
            ]);
        }

        return $variant;
    }

    protected function prepareLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'At least one ingredient line is required.',
            ]);
        }

        $prepared = [];
        $ingredientIds = [];

        foreach ($lines as $index => $line) {
            $ingredientId = (int) ($line['ingredient_id'] ?? 0);
            $ingredient = $this->recipes->findActiveIngredient($ingredientId);

            if (! $ingredient) {
                throw ValidationException::withMessages([
                    "lines.$index.ingredient_id" => 'Only active ingredients can be used in a recipe.',
                ]);
            }

            if (in_array($ingredientId, $ingredientIds, true)) {
                throw ValidationException::withMessages([
                    "lines.$index.ingredient_id" => 'Duplicate ingredients are not allowed in the same recipe.',
                ]);
            }

            $ingredientIds[] = $ingredientId;

            $measurementUnit = IngredientUnit::from((string) $line['measurement_unit']);
            $baseUnit = $ingredient->base_measurement_unit;

            if (! $baseUnit instanceof IngredientUnit || ! $measurementUnit->supportsBaseUnit($baseUnit)) {
                throw ValidationException::withMessages([
                    "lines.$index.measurement_unit" => 'Selected unit is not compatible with this ingredient.',
                ]);
            }

            $quantity = bcdiv((string) $line['quantity'], '1', 3);
            $baseQuantity = $measurementUnit->normalize($quantity, 3);

            $prepared[] = [
                'id' => $line['id'] ?? null,
                'ingredient_id' => $ingredientId,
                'quantity' => $quantity,
                'measurement_unit' => $measurementUnit->value,
                'base_quantity' => $baseQuantity,
                'base_measurement_unit' => $baseUnit->value,
                'sort_order' => (int) ($line['sort_order'] ?? ($index + 1)),
                'show_to_customer' => (bool) ($line['show_to_customer'] ?? false),
                'customer_label' => filled($line['customer_label'] ?? null)
                    ? trim((string) $line['customer_label'])
                    : null,
            ];
        }

        return $prepared;
    }
}
