<?php

namespace Database\Seeders;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RecipeSeeder extends Seeder
{
    /**
     * Products intentionally left without recipes for readiness demos.
     *
     * @var list<string>
     */
    protected array $skipRecipeProducts = [
        'Draft Rose Latte',
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach ($this->recipes() as $definition) {
            $this->seedRecipeDefinition($definition);
        }

        $this->fillMissingRecipes();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function seedRecipeDefinition(array $definition): void
    {
        $product = Product::query()->where('name', $definition['product'])->first();

        if (! $product) {
            return;
        }

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('name', $definition['variant'])
            ->first();

        if (! $variant) {
            return;
        }

        $this->persistRecipe($variant, $definition['notes'], $definition['lines']);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function persistRecipe(ProductVariant $variant, string $notes, array $lines): void
    {
        $recipe = Recipe::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            [
                'version' => 1,
                'preparation_notes' => $notes,
                'is_active' => true,
            ],
        );

        $recipe->lines()->delete();

        foreach ($lines as $index => $line) {
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
                'show_to_customer' => (bool) ($line['show_to_customer'] ?? false),
                'customer_label' => $line['customer_label'] ?? null,
            ]);
        }
    }

    protected function fillMissingRecipes(): void
    {
        Product::query()
            ->with('variants')
            ->orderBy('name')
            ->each(function (Product $product): void {
                if (in_array($product->name, $this->skipRecipeProducts, true)) {
                    return;
                }

                foreach ($product->variants as $variant) {
                    if (Recipe::query()->where('product_variant_id', $variant->id)->exists()) {
                        continue;
                    }

                    $this->persistRecipe(
                        $variant,
                        'DEMO auto recipe for '.$product->name.' ('.$variant->name.').',
                        $this->defaultLinesFor($product->name, (string) $variant->name),
                    );
                }
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaultLinesFor(string $productName, string $variantName): array
    {
        $scale = $this->variantScale($variantName);
        $name = Str::lower($productName);

        if (Str::contains($name, ['croissant', 'muffin'])) {
            $pastry = Str::contains($name, 'muffin')
                ? 'Demo Chocolate Muffin'
                : 'Demo Butter Croissant';

            return [
                $this->line($pastry, bcmul('1.000', $scale, 3), IngredientUnit::Piece, true, 'Pastry'),
            ];
        }

        if (Str::contains($name, ['mojito', 'lemonade', 'refresher'])) {
            $lines = [
                $this->line('Fresh Lime', bcmul('20.000', $scale, 3), IngredientUnit::Gram, true, 'Lime'),
                $this->line('Soda Water', bcmul('220.000', $scale, 3), IngredientUnit::Milliliter, false),
                $this->line('Cubed Ice', bcmul('100.000', $scale, 3), IngredientUnit::Gram, false),
            ];

            if (Str::contains($name, 'strawberry')) {
                $lines[] = $this->line('Strawberry Syrup', bcmul('0.015', $scale, 3), IngredientUnit::Bottle, true, 'Strawberry');
            }

            if (Str::contains($name, ['blueberry', 'berry'])) {
                $lines[] = $this->line('Honey', bcmul('10.000', $scale, 3), IngredientUnit::Gram, true, 'Honey');
            }

            return $lines;
        }

        if (Str::contains($name, 'matcha')) {
            $lines = [
                $this->line('Ceremonial Matcha', bcmul('3.000', $scale, 3), IngredientUnit::Gram, true, 'Matcha'),
                $this->line('Full Fat Milk', bcmul('220.000', $scale, 3), IngredientUnit::Milliliter, true, 'Milk'),
            ];

            if (Str::contains($name, 'iced')) {
                $lines[] = $this->line('Cubed Ice', bcmul('100.000', $scale, 3), IngredientUnit::Gram, false);
            }

            return $lines;
        }

        if (Str::contains($name, 'chai') || Str::contains($name, 'tea')) {
            return [
                $this->line('Assam Tea Leaves', bcmul('8.000', $scale, 3), IngredientUnit::Gram, true, 'Assam tea'),
                $this->line('Full Fat Milk', bcmul('180.000', $scale, 3), IngredientUnit::Milliliter, true, 'Milk'),
            ];
        }

        if (Str::contains($name, 'frappe')) {
            $lines = [
                $this->line('Davidoff Espresso', bcmul('16.000', $scale, 3), IngredientUnit::Gram, true, 'Espresso'),
                $this->line('Full Fat Milk', bcmul('150.000', $scale, 3), IngredientUnit::Milliliter, true, 'Milk'),
                $this->line('Vanilla Ice Cream', bcmul('80.000', $scale, 3), IngredientUnit::Gram, false),
                $this->line('Cubed Ice', bcmul('150.000', $scale, 3), IngredientUnit::Gram, false),
            ];

            if (Str::contains($name, ['mocha', 'chocolate'])) {
                $lines[] = $this->line('Chocolate Sauce', bcmul('30.000', $scale, 3), IngredientUnit::Gram, true, 'Chocolate');
            }

            if (Str::contains($name, 'vanilla')) {
                $lines[] = $this->line('Vanilla Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Vanilla');
            }

            if (Str::contains($name, 'caramel')) {
                $lines[] = $this->line('Caramel Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Caramel');
            }

            if (Str::contains($name, 'hazelnut')) {
                $lines[] = $this->line('Hazelnut Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Hazelnut');
            }

            if (Str::contains($name, 'strawberry')) {
                $lines[] = $this->line('Strawberry Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Strawberry');
            }

            return $lines;
        }

        if (Str::contains($name, ['cold brew', 'cold coffee', 'iced'])) {
            $lines = [
                $this->line('Davidoff Espresso', bcmul('14.000', $scale, 3), IngredientUnit::Gram, true, 'Coffee'),
                $this->line('Cubed Ice', bcmul('120.000', $scale, 3), IngredientUnit::Gram, false),
            ];

            if (Str::contains($name, ['latte', 'milk', 'classic cold coffee'])) {
                $lines[] = $this->line('Full Fat Milk', bcmul('180.000', $scale, 3), IngredientUnit::Milliliter, true, 'Milk');
            }

            if (Str::contains($name, 'tonic')) {
                $lines[] = $this->line('Tonic Water', bcmul('180.000', $scale, 3), IngredientUnit::Milliliter, false);
            }

            if (Str::contains($name, 'honey') || Str::contains($name, 'lime')) {
                $lines[] = $this->line('Honey', bcmul('12.000', $scale, 3), IngredientUnit::Gram, true, 'Honey');
                $lines[] = $this->line('Fresh Lime', bcmul('10.000', $scale, 3), IngredientUnit::Gram, true, 'Lime');
            }

            if (Str::contains($name, 'caramel')) {
                $lines[] = $this->line('Caramel Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Caramel');
            }

            if (Str::contains($name, 'vanilla')) {
                $lines[] = $this->line('Vanilla Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Vanilla');
            }

            return $lines;
        }

        // Hot coffee defaults
        $lines = [
            $this->line('Davidoff Espresso', bcmul('18.000', $scale, 3), IngredientUnit::Gram, true, 'Espresso'),
        ];

        $isStraightEspresso = Str::contains($name, 'espresso') && ! Str::contains($name, 'latte');
        $isAmericanoStyle = Str::contains($name, 'americano') && ! Str::contains($name, 'latte');

        if (! $isStraightEspresso && ! $isAmericanoStyle) {
            $lines[] = $this->line('Full Fat Milk', bcmul('200.000', $scale, 3), IngredientUnit::Milliliter, true, 'Milk');
        }

        if (Str::contains($name, ['mocha', 'chocolate'])) {
            $lines[] = $this->line('Chocolate Sauce', bcmul('25.000', $scale, 3), IngredientUnit::Gram, true, 'Chocolate');
        }

        if (Str::contains($name, 'hazelnut')) {
            $lines[] = $this->line('Hazelnut Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Hazelnut');
        }

        if (Str::contains($name, 'caramel')) {
            $lines[] = $this->line('Caramel Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Caramel');
        }

        if (Str::contains($name, 'vanilla')) {
            $lines[] = $this->line('Vanilla Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Vanilla');
        }

        if (Str::contains($name, 'irish')) {
            $lines[] = $this->line('Irish Syrup', bcmul('0.020', $scale, 3), IngredientUnit::Bottle, true, 'Irish');
        }

        if (Str::contains($name, 'sugar')) {
            $lines[] = $this->line('White Sugar', bcmul('5.000', $scale, 3), IngredientUnit::Gram, false);
        }

        return $lines;
    }

    protected function variantScale(string $variantName): string
    {
        $normalized = Str::lower($variantName);

        return match (true) {
            Str::contains($normalized, ['large', '500', 'double']) => '1.350',
            default => '1.000',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function line(
        string $ingredient,
        string $quantity,
        IngredientUnit $unit,
        bool $showToCustomer = false,
        ?string $customerLabel = null,
    ): array {
        return [
            'ingredient' => $ingredient,
            'quantity' => $quantity,
            'unit' => $unit,
            'base_quantity' => $quantity,
            'base_unit' => $unit,
            'show_to_customer' => $showToCustomer,
            'customer_label' => $customerLabel,
        ];
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
                    $this->line('Davidoff Espresso', '18.000', IngredientUnit::Gram, true, 'Espresso'),
                    $this->line('Full Fat Milk', '200.000', IngredientUnit::Milliliter, true, 'Milk'),
                ],
            ],
            [
                'product' => 'Cafe Latte',
                'variant' => 'Large',
                'notes' => 'Large latte build with additional steamed milk.',
                'lines' => [
                    $this->line('Davidoff Espresso', '22.000', IngredientUnit::Gram, true, 'Espresso'),
                    $this->line('Full Fat Milk', '280.000', IngredientUnit::Milliliter, true, 'Milk'),
                ],
            ],
            [
                'product' => 'Iced Vanilla Latte',
                'variant' => 'Regular',
                'notes' => 'Build over ice; add vanilla syrup before milk.',
                'lines' => [
                    $this->line('Davidoff Espresso', '18.000', IngredientUnit::Gram, true, 'Espresso'),
                    $this->line('Full Fat Milk', '180.000', IngredientUnit::Milliliter, true, 'Milk'),
                    $this->line('Vanilla Syrup', '0.020', IngredientUnit::Bottle, true, 'Vanilla'),
                    $this->line('Cubed Ice', '120.000', IngredientUnit::Gram, false),
                ],
            ],
            [
                'product' => 'Mocha Frappe',
                'variant' => 'Regular',
                'notes' => 'Blend coffee, milk, chocolate, and ice until smooth.',
                'lines' => [
                    $this->line('Davidoff Espresso', '16.000', IngredientUnit::Gram, true, 'Espresso'),
                    $this->line('Full Fat Milk', '150.000', IngredientUnit::Milliliter, true, 'Milk'),
                    $this->line('Chocolate Sauce', '30.000', IngredientUnit::Gram, true, 'Chocolate'),
                    $this->line('Vanilla Ice Cream', '80.000', IngredientUnit::Gram, false),
                    $this->line('Cubed Ice', '150.000', IngredientUnit::Gram, false),
                ],
            ],
            [
                'product' => 'Matcha Latte',
                'variant' => 'Regular',
                'notes' => 'Whisk matcha with a little water, then add steamed milk.',
                'lines' => [
                    $this->line('Ceremonial Matcha', '3.000', IngredientUnit::Gram, true, 'Matcha'),
                    $this->line('Full Fat Milk', '240.000', IngredientUnit::Milliliter, true, 'Milk'),
                ],
            ],
            [
                'product' => 'Classic Masala Chai',
                'variant' => 'Regular',
                'notes' => 'Simmer tea with milk; sweetener optional at service.',
                'lines' => [
                    $this->line('Assam Tea Leaves', '8.000', IngredientUnit::Gram, true, 'Assam tea'),
                    $this->line('Full Fat Milk', '180.000', IngredientUnit::Milliliter, false),
                ],
            ],
            [
                'product' => 'Espresso',
                'variant' => 'Single',
                'notes' => 'Single shot.',
                'lines' => [
                    $this->line('Davidoff Espresso', '9.000', IngredientUnit::Gram, true, 'Espresso'),
                ],
            ],
            [
                'product' => 'Americano',
                'variant' => 'Regular',
                'notes' => 'Espresso lengthened with hot water.',
                'lines' => [
                    $this->line('Davidoff Espresso', '18.000', IngredientUnit::Gram, true, 'Espresso'),
                ],
            ],
            [
                'product' => 'Cappuccino',
                'variant' => 'Regular',
                'notes' => 'Equal parts espresso, milk, foam.',
                'lines' => [
                    $this->line('Davidoff Espresso', '18.000', IngredientUnit::Gram, true, 'Espresso'),
                    $this->line('Full Fat Milk', '140.000', IngredientUnit::Milliliter, true, 'Milk'),
                ],
            ],
            [
                'product' => 'Paused Cortado',
                'variant' => 'Regular',
                'notes' => 'Paused product still has a valid recipe for readiness testing.',
                'lines' => [
                    $this->line('Davidoff Espresso', '18.000', IngredientUnit::Gram, true, 'Espresso'),
                    $this->line('Full Fat Milk', '60.000', IngredientUnit::Milliliter, true, 'Milk'),
                ],
            ],
            [
                'product' => 'Sugar-Free Americano Special',
                'variant' => 'Regular',
                'notes' => 'Intentionally uses out-of-stock White Sugar for stock-concern demos.',
                'lines' => [
                    $this->line('Davidoff Espresso', '18.000', IngredientUnit::Gram, true, 'Espresso'),
                    $this->line('White Sugar', '5.000', IngredientUnit::Gram, false),
                ],
            ],
            [
                'product' => 'Virgin Mojito',
                'variant' => '300 ml',
                'notes' => 'Muddle lime, top with soda and ice.',
                'lines' => [
                    $this->line('Fresh Lime', '20.000', IngredientUnit::Gram, true, 'Lime'),
                    $this->line('Soda Water', '220.000', IngredientUnit::Milliliter, false),
                    $this->line('Cubed Ice', '100.000', IngredientUnit::Gram, false),
                ],
            ],
            [
                'product' => 'Cold Brew Tonic',
                'variant' => '300 ml',
                'notes' => 'Cold brew over ice with tonic.',
                'lines' => [
                    $this->line('Davidoff Espresso', '12.000', IngredientUnit::Gram, true, 'Coffee'),
                    $this->line('Tonic Water', '180.000', IngredientUnit::Milliliter, false),
                    $this->line('Cubed Ice', '100.000', IngredientUnit::Gram, false),
                ],
            ],
            [
                'product' => 'Iced Matcha Latte',
                'variant' => 'Regular',
                'notes' => 'Whisk matcha, build over ice.',
                'lines' => [
                    $this->line('Ceremonial Matcha', '3.000', IngredientUnit::Gram, true, 'Matcha'),
                    $this->line('Full Fat Milk', '220.000', IngredientUnit::Milliliter, true, 'Milk'),
                    $this->line('Cubed Ice', '100.000', IngredientUnit::Gram, false),
                ],
            ],
            [
                'product' => 'Hazelnut Latte',
                'variant' => 'Regular',
                'notes' => 'Latte with hazelnut syrup.',
                'lines' => [
                    $this->line('Davidoff Espresso', '18.000', IngredientUnit::Gram, true, 'Espresso'),
                    $this->line('Full Fat Milk', '200.000', IngredientUnit::Milliliter, true, 'Milk'),
                    $this->line('Hazelnut Syrup', '0.020', IngredientUnit::Bottle, true, 'Hazelnut'),
                ],
            ],
        ];
    }
}
