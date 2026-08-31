<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use App\Models\Product;
use Illuminate\Database\Seeder;

class HomeSectionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $sections = [
            [
                'name' => 'Pickup-ready picks',
                'title' => 'Pickup-ready picks',
                'slug' => 'pickup-ready-picks',
                'subtitle' => 'Featured',
                'sort_order' => 10,
                'max_items' => 8,
                'product_names' => [
                    'Cafe Latte',
                    'Cappuccino',
                    'Flat White',
                    'Americano',
                    'Iced Vanilla Latte',
                    'Cold Brew',
                ],
            ],
            [
                'name' => 'New on the menu',
                'title' => 'New on the menu',
                'slug' => 'new-on-the-menu',
                'subtitle' => 'Just landed',
                'sort_order' => 20,
                'max_items' => 8,
                'product_names' => [
                    'Matcha Latte',
                    'Mocha Frappe',
                    'Caramel Crunch Frappe',
                    'Hazelnut Mocha',
                ],
            ],
            [
                'name' => 'Bestsellers',
                'title' => 'Bestsellers',
                'slug' => 'bestsellers',
                'subtitle' => 'Customer favourites',
                'sort_order' => 30,
                'max_items' => 8,
                'product_names' => [
                    'Cold Brew',
                    'Cafe Latte',
                    'Classic Masala Chai',
                    'Butter Croissant',
                    'Iced Americano',
                ],
            ],
        ];

        foreach ($sections as $definition) {
            $section = HomeSection::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'title' => $definition['title'],
                    'subtitle' => $definition['subtitle'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                    'max_items' => $definition['max_items'],
                ],
            );

            $sync = [];
            $order = 10;

            foreach ($definition['product_names'] as $productName) {
                $product = Product::query()->where('name', $productName)->first();

                if (! $product) {
                    continue;
                }

                $sync[$product->id] = ['sort_order' => $order];
                $order += 10;
            }

            $section->products()->sync($sync);
        }
    }
}
