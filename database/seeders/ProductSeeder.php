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
        $products = [
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
                'flavours' => ['Vanilla', 'Hazelnut'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => ProductServingUnit::Milliliter->value, 'price' => '4.75', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '350.000', 'serving_size_unit' => ProductServingUnit::Milliliter->value, 'price' => '5.75', 'sort_order' => 2],
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
                'sort_order' => 2,
                'is_featured' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => ProductServingUnit::Milliliter->value, 'price' => '5.25', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '450.000', 'serving_size_unit' => ProductServingUnit::Milliliter->value, 'price' => '6.25', 'sort_order' => 2],
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
                'sort_order' => 3,
                'is_featured' => false,
                'flavours' => ['Mocha', 'Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => ProductServingUnit::Milliliter->value, 'price' => '6.50', 'sort_order' => 1],
                ],
            ],
        ];

        foreach ($products as $productData) {
            $category = ProductCategory::query()->where('name', $productData['category'])->firstOrFail();

            $product = Product::query()->updateOrCreate(
                ['slug' => Str::slug($productData['name'])],
                Arr::except(array_merge($productData, [
                    'product_category_id' => $category->getKey(),
                    'image_path' => null,
                    'is_active' => true,
                    'is_available' => true,
                ]), ['category', 'flavours', 'variants']),
            );

            $flavourIds = ProductFlavour::query()
                ->whereIn('name', $productData['flavours'])
                ->pluck('id')
                ->all();

            $product->flavours()->sync($flavourIds);

            foreach ($productData['variants'] as $variant) {
                $product->variants()->updateOrCreate(
                    ['name' => $variant['name']],
                    array_merge($variant, [
                        'is_active' => true,
                        'is_available' => true,
                    ]),
                );
            }
        }
    }
}
