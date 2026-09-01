<?php

namespace Database\Seeders;

use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoFoodCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $categories = [
            'Breakfast' => 'Morning plates and bakery breakfast.',
            'Snacks' => 'Light bites between rounds.',
            'Sandwiches' => 'Toasted and cold sandwiches.',
            'Pasta' => 'Kitchen pasta bowls.',
            'Desserts' => 'Sweet finishes for table service.',
        ];

        $sort = 100;
        foreach ($categories as $name => $description) {
            ProductCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'image_path' => null,
                    'sort_order' => $sort,
                    'is_active' => true,
                ],
            );
            $sort += 10;
        }

        $foods = [
            ['category' => 'Breakfast', 'name' => 'Masala Omelette', 'price' => '9.50', 'prep' => 12, 'veg' => true],
            ['category' => 'Breakfast', 'name' => 'Avocado Toast', 'price' => '8.75', 'prep' => 10, 'veg' => true],
            ['category' => 'Snacks', 'name' => 'Loaded Fries', 'price' => '7.25', 'prep' => 12, 'veg' => true],
            ['category' => 'Snacks', 'name' => 'Chicken Nuggets', 'price' => '6.50', 'prep' => 10, 'veg' => false],
            ['category' => 'Sandwiches', 'name' => 'Club Sandwich', 'price' => '11.00', 'prep' => 15, 'veg' => false],
            ['category' => 'Sandwiches', 'name' => 'Grilled Cheese', 'price' => '7.00', 'prep' => 8, 'veg' => true],
            ['category' => 'Pasta', 'name' => 'Creamy Penne', 'price' => '12.50', 'prep' => 18, 'veg' => true],
            ['category' => 'Pasta', 'name' => 'Arrabbiata Bowl', 'price' => '11.75', 'prep' => 16, 'veg' => true],
            ['category' => 'Desserts', 'name' => 'Chocolate Brownie', 'price' => '5.50', 'prep' => 5, 'veg' => true],
            ['category' => 'Desserts', 'name' => 'Basque Cheesecake', 'price' => '6.75', 'prep' => 5, 'veg' => true],
        ];

        foreach ($foods as $index => $food) {
            $category = ProductCategory::query()->where('name', $food['category'])->firstOrFail();

            $product = Product::query()->updateOrCreate(
                ['slug' => Str::slug($food['name'])],
                [
                    'product_category_id' => $category->getKey(),
                    'name' => $food['name'],
                    'sku' => 'FOOD-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'short_description' => $food['name'].' prepared in the kitchen.',
                    'description' => $food['name'].' for dining and takeaway service.',
                    'customer_ingredient_summary' => 'Kitchen prepared',
                    'image_path' => '/demo/product-placeholder.svg',
                    'preparation_time_minutes' => $food['prep'],
                    'sort_order' => $index + 1,
                    'product_type' => ProductType::Food,
                    'preparation_station' => PreparationStation::Kitchen,
                    'is_active' => true,
                    'is_available' => true,
                    'is_featured' => false,
                    'is_new' => true,
                    'is_bestseller' => false,
                    'is_vegetarian' => $food['veg'],
                    'is_customizable' => false,
                ],
            );

            $product->variants()->updateOrCreate(
                ['name' => 'Regular'],
                [
                    'serving_size_value' => '1.000',
                    'serving_size_unit' => ProductServingUnit::Piece,
                    'price' => $food['price'],
                    'sort_order' => 1,
                    'is_active' => true,
                    'is_available' => true,
                ],
            );
        }

        Product::query()
            ->where(function ($query): void {
                $query->whereNull('product_type')->orWhere('product_type', ProductType::Beverage->value);
            })
            ->update([
                'product_type' => ProductType::Beverage->value,
                'preparation_station' => PreparationStation::Bar->value,
            ]);
    }
}
