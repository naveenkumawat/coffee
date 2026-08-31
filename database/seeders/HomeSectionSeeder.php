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
                'name' => 'Featured Drinks',
                'title' => 'Featured Drinks',
                'slug' => 'featured-drinks',
                'subtitle' => 'Featured',
                'sort_order' => 10,
                'max_items' => 8,
                'is_active' => true,
                'product_names' => [
                    'Cafe Latte',
                    'Cappuccino',
                    'Hazelnut Latte',
                    'Iced Vanilla Latte',
                    'Classic Cold Brew',
                    'Virgin Mojito',
                ],
            ],
            [
                'name' => 'Bestsellers',
                'title' => 'Bestsellers',
                'slug' => 'bestsellers',
                'subtitle' => 'Customer favourites',
                'sort_order' => 20,
                'max_items' => 8,
                'is_active' => true,
                'product_names' => [
                    'Cold Brew',
                    'Cafe Latte',
                    'Classic Masala Chai',
                    'Classic Cold Coffee',
                    'Butter Croissant',
                    'Iced Americano',
                ],
            ],
            [
                'name' => 'New This Week',
                'title' => 'New This Week',
                'slug' => 'new-this-week',
                'subtitle' => 'Just landed',
                'sort_order' => 30,
                'max_items' => 8,
                'is_active' => true,
                'product_names' => [
                    'Honey Lime Cold Brew',
                    'Strawberry Frappe',
                    'Mix Berry Lemonade',
                    'Matcha Latte',
                    'Flat White',
                ],
            ],
            [
                'name' => 'Cold Favourites',
                'title' => 'Cold Favourites',
                'slug' => 'cold-favourites',
                'subtitle' => 'Chilled picks',
                'sort_order' => 40,
                'max_items' => 8,
                'is_active' => true,
                'product_names' => [
                    'Iced Vanilla Latte',
                    'Cold Brew',
                    'Classic Cold Brew',
                    'Iced Matcha Latte',
                    'Caramel Iced Latte',
                    'Mocha Frappe',
                ],
            ],
            [
                'name' => 'Refreshers',
                'title' => 'Refreshers',
                'slug' => 'refreshers',
                'subtitle' => 'Coolers',
                'sort_order' => 50,
                'max_items' => 6,
                'is_active' => true,
                'product_names' => [
                    'Virgin Mojito',
                    'Strawberry Mojito',
                    'Blueberry Mojito',
                    'Mix Berry Lemonade',
                    'Cold Brew Tonic',
                ],
            ],
            [
                'name' => 'Archived Promo Rail',
                'title' => 'Archived Promo Rail',
                'slug' => 'archived-promo-rail',
                'subtitle' => 'Inactive section for admin testing',
                'sort_order' => 99,
                'max_items' => 4,
                'is_active' => false,
                'product_names' => [
                    'Seasonal Spice Latte',
                    'Irish Latte',
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
                    'is_active' => $definition['is_active'],
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

        HomeSection::query()->whereIn('slug', [
            'pickup-ready-picks',
            'new-on-the-menu',
        ])->update(['is_active' => false]);
    }
}
