<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Hot Coffee', 'description' => 'Espresso-based favourites for daily cafe service.', 'sort_order' => 1],
            ['name' => 'Cold Coffee', 'description' => 'Chilled coffee drinks for all-day service.', 'sort_order' => 2],
            ['name' => 'Frappes', 'description' => 'Blended ice-based beverages with dessert-style presentation.', 'sort_order' => 3],
            ['name' => 'Tea & Matcha', 'description' => 'Tea-forward and matcha-based drinks.', 'sort_order' => 4],
            ['name' => 'Pastries', 'description' => 'Baked pairings for coffee and tea orders.', 'sort_order' => 5],
        ];

        foreach ($categories as $category) {
            ProductCategory::query()->updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'image_path' => null,
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
