<?php

namespace Database\Seeders;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach ($this->recipes() as $definition) {
            $product = Product::query()->where('name', $definition['product'])->first();

            if (! $product) {
                continue;
            }

            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('name', $definition['variant'])
                ->first();

            if (! $variant) {
                continue;
            }

            $recipe = Recipe::query()->updateOrCreate(
                ['product_variant_id' => $variant->id],
                [
                    'version' => 1,
                    'preparation_notes' => $definition['notes'],
                    'is_active' => true,
                ],
            );

            $recipe->lines()->delete();

            foreach ($definition['lines'] as $index => $line) {
                $ingredient = Ingredient::query()->where('name', $line['ingredient'])->first();

                if (! $ingredient) {
                    continue;
                }

                $recipe->lines()->create([
                    'ingredient_id' => $ingredient->id,
                    'quantity' => $line['quantity'],
                    'measurement_unit' => $line['unit'],
                    'base_quantity' => $line['base_quantity'],
                    'base_measurement_unit' => $line['base_unit'],
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recipes(): array
    {
        return [
            [
                'product' => 'Cafe Latte',
                'variant' => 'Regular',
                'notes' => 'Pull double espresso, steam milk to 65C, combine and serve.',
                'lines' => [
                    [
                        'ingredient' => 'Davidoff Espresso',
                        'quantity' => '18.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '18.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                    [
                        'ingredient' => 'Full Fat Milk',
                        'quantity' => '200.000',
                        'unit' => IngredientUnit::Milliliter,
                        'base_quantity' => '200.000',
                        'base_unit' => IngredientUnit::Milliliter,
                    ],
                ],
            ],
            [
                'product' => 'Cafe Latte',
                'variant' => 'Large',
                'notes' => 'Large latte build with additional steamed milk.',
                'lines' => [
                    [
                        'ingredient' => 'Davidoff Espresso',
                        'quantity' => '22.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '22.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                    [
                        'ingredient' => 'Full Fat Milk',
                        'quantity' => '280.000',
                        'unit' => IngredientUnit::Milliliter,
                        'base_quantity' => '280.000',
                        'base_unit' => IngredientUnit::Milliliter,
                    ],
                ],
            ],
            [
                'product' => 'Iced Vanilla Latte',
                'variant' => 'Regular',
                'notes' => 'Build over ice; add vanilla syrup before milk.',
                'lines' => [
                    [
                        'ingredient' => 'Davidoff Espresso',
                        'quantity' => '18.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '18.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                    [
                        'ingredient' => 'Full Fat Milk',
                        'quantity' => '180.000',
                        'unit' => IngredientUnit::Milliliter,
                        'base_quantity' => '180.000',
                        'base_unit' => IngredientUnit::Milliliter,
                    ],
                    [
                        'ingredient' => 'Vanilla Syrup',
                        'quantity' => '0.020',
                        'unit' => IngredientUnit::Bottle,
                        'base_quantity' => '0.020',
                        'base_unit' => IngredientUnit::Bottle,
                    ],
                    [
                        'ingredient' => 'Cubed Ice',
                        'quantity' => '120.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '120.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                ],
            ],
            [
                'product' => 'Mocha Frappe',
                'variant' => 'Regular',
                'notes' => 'Blend coffee, milk, chocolate, and ice until smooth.',
                'lines' => [
                    [
                        'ingredient' => 'Davidoff Espresso',
                        'quantity' => '16.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '16.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                    [
                        'ingredient' => 'Full Fat Milk',
                        'quantity' => '150.000',
                        'unit' => IngredientUnit::Milliliter,
                        'base_quantity' => '150.000',
                        'base_unit' => IngredientUnit::Milliliter,
                    ],
                    [
                        'ingredient' => 'Chocolate Sauce',
                        'quantity' => '30.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '30.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                    [
                        'ingredient' => 'Vanilla Ice Cream',
                        'quantity' => '80.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '80.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                    [
                        'ingredient' => 'Cubed Ice',
                        'quantity' => '150.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '150.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                ],
            ],
            [
                'product' => 'Matcha Latte',
                'variant' => 'Regular',
                'notes' => 'Whisk matcha with a little water, then add steamed milk.',
                'lines' => [
                    [
                        'ingredient' => 'Ceremonial Matcha',
                        'quantity' => '3.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '3.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                    [
                        'ingredient' => 'Full Fat Milk',
                        'quantity' => '240.000',
                        'unit' => IngredientUnit::Milliliter,
                        'base_quantity' => '240.000',
                        'base_unit' => IngredientUnit::Milliliter,
                    ],
                ],
            ],
            [
                'product' => 'Classic Masala Chai',
                'variant' => 'Regular',
                'notes' => 'Simmer tea with milk; sweetener optional at service.',
                'lines' => [
                    [
                        'ingredient' => 'Assam Tea Leaves',
                        'quantity' => '8.000',
                        'unit' => IngredientUnit::Gram,
                        'base_quantity' => '8.000',
                        'base_unit' => IngredientUnit::Gram,
                    ],
                    [
                        'ingredient' => 'Full Fat Milk',
                        'quantity' => '180.000',
                        'unit' => IngredientUnit::Milliliter,
                        'base_quantity' => '180.000',
                        'base_unit' => IngredientUnit::Milliliter,
                    ],
                ],
            ],
        ];
    }
}
