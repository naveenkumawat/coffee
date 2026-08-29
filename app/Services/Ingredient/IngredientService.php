<?php

namespace App\Services\Ingredient;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Repositories\Ingredient\IngredientRepositoryInterface;
use App\Transfers\Ingredient\IngredientTransferInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IngredientService implements IngredientServiceInterface
{
    public function __construct(
        protected IngredientRepositoryInterface $ingredients,
    ) {}

    public function store(IngredientTransferInterface $data): Ingredient
    {
        return DB::transaction(function () use ($data): Ingredient {
            return $this->ingredients->create($this->prepareAttributes($data));
        });
    }

    public function update(Ingredient $ingredient, IngredientTransferInterface $data): Ingredient
    {
        return DB::transaction(function () use ($ingredient, $data): Ingredient {
            return $this->ingredients->update($ingredient, $this->prepareAttributes($data, $ingredient));
        });
    }

    public function delete(Ingredient $ingredient): void
    {
        DB::transaction(function () use ($ingredient): void {
            $ingredient->forceFill(['is_active' => false])->save();
            $this->ingredients->delete($ingredient);
        });
    }

    protected function prepareAttributes(IngredientTransferInterface $data, ?Ingredient $ingredient = null): array
    {
        $unit = IngredientUnit::from((string) $data->getMeasurementUnit());
        $purchaseQuantityBase = $unit->normalize((string) $data->getPurchaseQuantity(), 3);
        $purchaseCost = $this->normalizeMoney((string) $data->getPurchaseCost());
        $costPerUnit = bcdiv($purchaseCost, $purchaseQuantityBase, 4);

        $minimumStock = $unit->normalize((string) ($data->getMinimumStock() ?? '0'), 3);
        $reorderLevel = $unit->normalize((string) ($data->getReorderLevel() ?? '0'), 3);
        $currentStock = $ingredient
            ? $this->normalizeDecimal((string) $ingredient->current_stock, 3)
            : '0.000';

        if (bccomp($reorderLevel, $minimumStock, 3) === -1) {
            throw ValidationException::withMessages([
                'reorder_level' => 'Reorder level must be greater than or equal to the minimum stock.',
            ]);
        }

        $slug = filled($data->getSlug()) ? Str::slug((string) $data->getSlug()) : Str::slug((string) $data->getName());

        return [
            'ingredient_category_id' => $data->getIngredientCategoryId(),
            'ingredient_brand_id' => $data->getIngredientBrandId(),
            'name' => $data->getName(),
            'slug' => $slug,
            'description' => $data->getDescription(),
            'measurement_unit' => $unit->value,
            'base_measurement_unit' => $unit->baseUnit()->value,
            'purchase_quantity' => $this->normalizeDecimal((string) $data->getPurchaseQuantity(), 3),
            'purchase_quantity_base' => $purchaseQuantityBase,
            'purchase_cost' => $purchaseCost,
            'cost_per_unit' => $costPerUnit,
            'current_stock' => $currentStock,
            'minimum_stock' => $minimumStock,
            'reorder_level' => $reorderLevel,
            'supplier_name' => $data->getSupplierName(),
            'supplier_email' => $data->getSupplierEmail(),
            'supplier_phone' => $data->getSupplierPhone(),
            'supplier_notes' => $data->getSupplierNotes(),
            'is_active' => $data->isActive(),
        ];
    }

    protected function normalizeMoney(string $value): string
    {
        return $this->normalizeDecimal($value, 2);
    }

    protected function normalizeDecimal(string $value, int $scale): string
    {
        return bcdiv($value, '1', $scale);
    }
}
