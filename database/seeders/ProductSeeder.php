<?php

namespace Database\Seeders;

use App\Enums\ProductServingUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach ($this->products() as $productData) {
            $category = ProductCategory::query()->where('name', $productData['category'])->firstOrFail();

            $product = Product::query()->updateOrCreate(
                ['slug' => Str::slug($productData['name'])],
                Arr::except(array_merge([
                    'product_category_id' => $category->getKey(),
                    'image_path' => null,
                    'is_active' => true,
                    'is_available' => true,
                    'is_featured' => false,
                    'is_new' => false,
                    'is_bestseller' => false,
                    'is_vegetarian' => true,
                    'is_customizable' => false,
                ], $productData), ['category', 'flavours', 'variants']),
            );

            $flavourIds = ProductFlavour::query()
                ->whereIn('name', $productData['flavours'])
                ->pluck('id')
                ->all();

            $product->flavours()->sync($flavourIds);

            foreach ($productData['variants'] as $variant) {
                $product->variants()->updateOrCreate(
                    ['name' => $variant['name']],
                    array_merge([
                        'is_active' => true,
                        'is_available' => true,
                    ], $variant),
                );
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function products(): array
    {
        $ml = ProductServingUnit::Milliliter->value;

        return [
            [
                'category' => 'Hot Coffee',
                'name' => 'Cafe Latte',
                'sku' => 'LATTE-REG',
                'short_description' => 'Espresso with silky steamed milk.',
                'description' => 'A balanced espresso and milk drink built for everyday cafe service.',
                'customer_ingredient_summary' => 'Espresso, milk',
                'preparation_time_minutes' => 4,
                'sort_order' => 1,
                'is_featured' => true,
                'is_bestseller' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla', 'Hazelnut', 'Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '4.75', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '5.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Cappuccino',
                'sku' => 'CAPPUCCINO',
                'short_description' => 'Espresso topped with dense milk foam.',
                'description' => 'Classic cappuccino with a thicker foam layer than latte.',
                'customer_ingredient_summary' => 'Espresso, milk foam',
                'preparation_time_minutes' => 4,
                'sort_order' => 2,
                'is_bestseller' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla', 'Hazelnut'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '180.000', 'serving_size_unit' => $ml, 'price' => '4.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '5.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Flat White',
                'sku' => 'FLAT-WHITE',
                'short_description' => 'Ristretto-forward espresso with microfoam.',
                'description' => 'Smaller milk drink with a stronger espresso presence.',
                'customer_ingredient_summary' => 'Espresso, steamed milk',
                'preparation_time_minutes' => 3,
                'sort_order' => 3,
                'is_new' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '200.000', 'serving_size_unit' => $ml, 'price' => '4.95', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Americano',
                'sku' => 'AMERICANO',
                'short_description' => 'Espresso lengthened with hot water.',
                'description' => 'Clean black coffee option for guests who want espresso clarity.',
                'customer_ingredient_summary' => 'Espresso, hot water',
                'preparation_time_minutes' => 2,
                'sort_order' => 4,
                'is_vegetarian' => true,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '3.75', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '4.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Hazelnut Mocha',
                'sku' => 'HAZ-MOCHA',
                'short_description' => 'Chocolate espresso with hazelnut syrup.',
                'description' => 'House mocha finished with hazelnut for a dessert-leaning hot drink.',
                'customer_ingredient_summary' => 'Espresso, milk, chocolate, hazelnut',
                'preparation_time_minutes' => 5,
                'sort_order' => 5,
                'is_featured' => true,
                'is_customizable' => true,
                'flavours' => ['Hazelnut', 'Mocha'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '400.000', 'serving_size_unit' => $ml, 'price' => '6.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Iced Vanilla Latte',
                'sku' => 'ICED-VAN-LATTE',
                'short_description' => 'Espresso, milk, and vanilla over ice.',
                'description' => 'A refreshing chilled latte with a clean vanilla finish.',
                'customer_ingredient_summary' => 'Espresso, milk, vanilla, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 1,
                'is_featured' => true,
                'is_bestseller' => true,
                'is_new' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.25', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '450.000', 'serving_size_unit' => $ml, 'price' => '6.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Iced Americano',
                'sku' => 'ICED-AMERICANO',
                'short_description' => 'Espresso over cold water and ice.',
                'description' => 'Bright iced black coffee for all-day service.',
                'customer_ingredient_summary' => 'Espresso, water, ice',
                'preparation_time_minutes' => 2,
                'sort_order' => 2,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '4.00', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '4.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Cold Brew',
                'sku' => 'COLD-BREW',
                'short_description' => 'Slow-steeped coffee served over ice.',
                'description' => 'Smooth cold brew with lower perceived acidity.',
                'customer_ingredient_summary' => 'Cold brew concentrate, ice',
                'preparation_time_minutes' => 2,
                'sort_order' => 3,
                'is_bestseller' => true,
                'flavours' => ['Vanilla', 'Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '5.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '6.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Caramel Iced Latte',
                'sku' => 'CARAMEL-ICED',
                'short_description' => 'Iced latte finished with caramel syrup.',
                'description' => 'Sweet iced latte built for flavour-forward guests.',
                'customer_ingredient_summary' => 'Espresso, milk, caramel, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 4,
                'is_customizable' => true,
                'flavours' => ['Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '450.000', 'serving_size_unit' => $ml, 'price' => '6.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Frappes',
                'name' => 'Mocha Frappe',
                'sku' => 'MOCHA-FRAPPE',
                'short_description' => 'Blended coffee, chocolate, and ice.',
                'description' => 'A dessert-style frappe topped with rich mocha flavour.',
                'customer_ingredient_summary' => 'Coffee, milk, mocha, ice',
                'preparation_time_minutes' => 5,
                'sort_order' => 1,
                'is_featured' => true,
                'is_bestseller' => true,
                'is_customizable' => true,
                'flavours' => ['Mocha', 'Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '7.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Frappes',
                'name' => 'Vanilla Bean Frappe',
                'sku' => 'VAN-FRAPPE',
                'short_description' => 'Blended vanilla coffee shake.',
                'description' => 'Creamy vanilla frappe with espresso backbone.',
                'customer_ingredient_summary' => 'Coffee, milk, vanilla, ice cream, ice',
                'preparation_time_minutes' => 5,
                'sort_order' => 2,
                'is_new' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.25', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Frappes',
                'name' => 'Caramel Crunch Frappe',
                'sku' => 'CARAMEL-FRAPPE',
                'short_description' => 'Caramel frappe with whipped finish.',
                'description' => 'Blended caramel drink finished with whipped cream.',
                'customer_ingredient_summary' => 'Coffee, milk, caramel, whipped cream, ice',
                'preparation_time_minutes' => 6,
                'sort_order' => 3,
                'is_customizable' => true,
                'flavours' => ['Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.75', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '7.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Tea & Matcha',
                'name' => 'Classic Masala Chai',
                'sku' => 'MASALA-CHAI',
                'short_description' => 'Spiced black tea with milk.',
                'description' => 'House chai brewed for pickup guests who want a non-coffee option.',
                'customer_ingredient_summary' => 'Tea, milk, spices, sugar',
                'preparation_time_minutes' => 5,
                'sort_order' => 1,
                'is_bestseller' => true,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '3.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '4.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Tea & Matcha',
                'name' => 'Matcha Latte',
                'sku' => 'MATCHA-LATTE',
                'short_description' => 'Whisked matcha with steamed milk.',
                'description' => 'Bright green tea latte available hot for cafe pickup.',
                'customer_ingredient_summary' => 'Matcha, milk',
                'preparation_time_minutes' => 4,
                'sort_order' => 2,
                'is_featured' => true,
                'is_new' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.75', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '400.000', 'serving_size_unit' => $ml, 'price' => '6.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Tea & Matcha',
                'name' => 'Iced Matcha Latte',
                'sku' => 'ICED-MATCHA',
                'short_description' => 'Chilled matcha latte over ice.',
                'description' => 'Refreshing iced matcha with lightly sweetened milk.',
                'customer_ingredient_summary' => 'Matcha, milk, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 3,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '6.95', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Pastries',
                'name' => 'Butter Croissant',
                'sku' => 'CROISSANT',
                'short_description' => 'Flaky butter croissant.',
                'description' => 'Baked daily for coffee pairings.',
                'customer_ingredient_summary' => 'Wheat flour, butter',
                'preparation_time_minutes' => 1,
                'sort_order' => 1,
                'is_vegetarian' => true,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Single', 'serving_size_value' => '1.000', 'serving_size_unit' => ProductServingUnit::Piece->value, 'price' => '3.25', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Pastries',
                'name' => 'Chocolate Muffin',
                'sku' => 'CHOC-MUFFIN',
                'short_description' => 'Rich chocolate muffin.',
                'description' => 'Soft muffin with chocolate chips for grab-and-go guests.',
                'customer_ingredient_summary' => 'Wheat flour, chocolate, eggs, sugar',
                'preparation_time_minutes' => 1,
                'sort_order' => 2,
                'is_new' => true,
                'is_vegetarian' => true,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Single', 'serving_size_value' => '1.000', 'serving_size_unit' => ProductServingUnit::Piece->value, 'price' => '3.75', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Seasonal Spice Latte',
                'sku' => 'SEASONAL-SPICE',
                'short_description' => 'Limited seasonal latte (currently unavailable).',
                'description' => 'Kept in catalog for unavailable-state UI testing.',
                'customer_ingredient_summary' => 'Espresso, milk, spice syrup',
                'preparation_time_minutes' => 4,
                'sort_order' => 99,
                'is_available' => false,
                'is_customizable' => true,
                'flavours' => ['Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 1],
                ],
            ],
        ];
    }
}
