<?php

namespace Database\Seeders;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\IngredientBrand;
use App\Models\IngredientCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach ($this->ingredients() as $ingredient) {
            $category = IngredientCategory::query()->where('name', $ingredient['category'])->first();
            $brand = filled($ingredient['brand'] ?? null)
                ? IngredientBrand::query()->where('name', $ingredient['brand'])->first()
                : null;

            if (! $category) {
                continue;
            }

            $record = Ingredient::query()->withTrashed()->firstOrNew([
                'slug' => Str::slug($ingredient['name']),
            ]);

            $record->fill([
                'ingredient_category_id' => $category->id,
                'ingredient_brand_id' => $brand?->id,
                'name' => $ingredient['name'],
                'description' => $ingredient['description'],
                'measurement_unit' => $ingredient['measurement_unit'],
                'base_measurement_unit' => $ingredient['base_measurement_unit'],
                'purchase_quantity' => $ingredient['purchase_quantity'],
                'purchase_quantity_base' => $ingredient['purchase_quantity_base'],
                'purchase_cost' => $ingredient['purchase_cost'],
                'cost_per_unit' => $ingredient['cost_per_unit'],
                'current_stock' => $ingredient['current_stock'],
                'minimum_stock' => $ingredient['minimum_stock'],
                'reorder_level' => $ingredient['reorder_level'],
                'supplier_name' => $ingredient['supplier_name'],
                'supplier_email' => $ingredient['supplier_email'],
                'supplier_phone' => $ingredient['supplier_phone'],
                'supplier_notes' => $ingredient['supplier_notes'],
                'is_active' => true,
            ]);
            $record->deleted_at = null;
            $record->save();
        }
    }

    protected function ingredients(): array
    {
        return [
            [
                'category' => 'Coffee',
                'name' => 'Nescafé Classic',
                'brand' => 'Nescafé',
                'description' => 'Instant coffee granules for quick-service beverages.',
                'measurement_unit' => IngredientUnit::Gram,
                'base_measurement_unit' => IngredientUnit::Gram,
                'purchase_quantity' => '100.000',
                'purchase_quantity_base' => '100.000',
                'purchase_cost' => '600.00',
                'cost_per_unit' => '6.0000',
                'current_stock' => '450.000',
                'minimum_stock' => '120.000',
                'reorder_level' => '180.000',
                'supplier_name' => 'Coffee Supply House',
                'supplier_email' => 'purchasing@coffeesupply.test',
                'supplier_phone' => '9999999991',
                'supplier_notes' => 'Core instant coffee stock.',
            ],
            [
                'category' => 'Coffee',
                'name' => 'Davidoff Espresso',
                'brand' => 'Davidoff',
                'description' => 'Premium espresso coffee for signature drinks.',
                'measurement_unit' => IngredientUnit::Gram,
                'base_measurement_unit' => IngredientUnit::Gram,
                'purchase_quantity' => '100.000',
                'purchase_quantity_base' => '100.000',
                'purchase_cost' => '600.00',
                'cost_per_unit' => '6.0000',
                'current_stock' => '320.000',
                'minimum_stock' => '100.000',
                'reorder_level' => '150.000',
                'supplier_name' => 'Coffee Supply House',
                'supplier_email' => 'purchasing@coffeesupply.test',
                'supplier_phone' => '9999999991',
                'supplier_notes' => 'Premium coffee stock.',
            ],
            [
                'category' => 'Milk',
                'name' => 'Full Fat Milk',
                'brand' => 'Amul',
                'description' => 'Daily milk stock for hot and cold beverages.',
                'measurement_unit' => IngredientUnit::Liter,
                'base_measurement_unit' => IngredientUnit::Milliliter,
                'purchase_quantity' => '1.000',
                'purchase_quantity_base' => '1000.000',
                'purchase_cost' => '70.00',
                'cost_per_unit' => '0.0700',
                'current_stock' => '12000.000',
                'minimum_stock' => '4000.000',
                'reorder_level' => '6000.000',
                'supplier_name' => 'Fresh Dairy Partner',
                'supplier_email' => 'orders@freshdairy.test',
                'supplier_phone' => '9999999992',
                'supplier_notes' => 'Refrigerated daily supply.',
            ],
            [
                'category' => 'Ice Cream',
                'name' => 'Vanilla Ice Cream',
                'brand' => 'Vadilal',
                'description' => 'Vanilla ice cream used in frappes and shakes.',
                'measurement_unit' => IngredientUnit::Kilogram,
                'base_measurement_unit' => IngredientUnit::Gram,
                'purchase_quantity' => '1.000',
                'purchase_quantity_base' => '1000.000',
                'purchase_cost' => '260.00',
                'cost_per_unit' => '0.2600',
                'current_stock' => '5000.000',
                'minimum_stock' => '1200.000',
                'reorder_level' => '1800.000',
                'supplier_name' => 'Dessert Wholesale Co.',
                'supplier_email' => 'ops@dessertwholesale.test',
                'supplier_phone' => '9999999993',
                'supplier_notes' => 'Frozen storage required.',
            ],
            [
                'category' => 'Syrups',
                'name' => 'Vanilla Syrup',
                'brand' => 'Monin',
                'description' => 'Vanilla syrup for coffees and frappes.',
                'measurement_unit' => IngredientUnit::Bottle,
                'base_measurement_unit' => IngredientUnit::Bottle,
                'purchase_quantity' => '1.000',
                'purchase_quantity_base' => '1.000',
                'purchase_cost' => '750.00',
                'cost_per_unit' => '750.0000',
                'current_stock' => '8.000',
                'minimum_stock' => '2.000',
                'reorder_level' => '3.000',
                'supplier_name' => 'Flavor Imports',
                'supplier_email' => 'sales@flavorimports.test',
                'supplier_phone' => '9999999994',
                'supplier_notes' => 'Bottle-based purchasing.',
            ],
            [
                'category' => 'Syrups',
                'name' => 'Hazelnut Syrup',
                'brand' => 'Monin',
                'description' => 'Hazelnut syrup for hot and iced coffee drinks.',
                'measurement_unit' => IngredientUnit::Bottle,
                'base_measurement_unit' => IngredientUnit::Bottle,
                'purchase_quantity' => '1.000',
                'purchase_quantity_base' => '1.000',
                'purchase_cost' => '800.00',
                'cost_per_unit' => '800.0000',
                'current_stock' => '5.000',
                'minimum_stock' => '2.000',
                'reorder_level' => '3.000',
                'supplier_name' => 'Flavor Imports',
                'supplier_email' => 'sales@flavorimports.test',
                'supplier_phone' => '9999999994',
                'supplier_notes' => 'Bottle-based purchasing.',
            ],
        ];
    }
}
