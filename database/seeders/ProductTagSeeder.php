<?php

namespace Database\Seeders;

use App\Enums\ProductTagStyle;
use App\Models\ProductTag;
use App\Support\ProductMarketingTags;
use Illuminate\Database\Seeder;

class ProductTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'New', 'slug' => ProductMarketingTags::NEW, 'style_key' => ProductTagStyle::Primary, 'sort_order' => 10],
            ['name' => 'Top Seller', 'slug' => ProductMarketingTags::TOP_SELLER, 'style_key' => ProductTagStyle::Accent, 'sort_order' => 20],
            ['name' => 'Featured', 'slug' => ProductMarketingTags::FEATURED, 'style_key' => ProductTagStyle::Soft, 'sort_order' => 30],
            ['name' => 'Seasonal', 'slug' => 'seasonal', 'style_key' => ProductTagStyle::Warning, 'sort_order' => 40],
            ['name' => 'Popular', 'slug' => 'popular', 'style_key' => ProductTagStyle::Accent, 'sort_order' => 50],
            ['name' => 'Limited', 'slug' => 'limited', 'style_key' => ProductTagStyle::Warning, 'sort_order' => 60],
            ['name' => 'Recommended', 'slug' => 'recommended', 'style_key' => ProductTagStyle::Muted, 'sort_order' => 70],
        ];

        foreach ($tags as $tag) {
            ProductTag::query()->updateOrCreate(
                ['slug' => $tag['slug']],
                [
                    'name' => $tag['name'],
                    'style_key' => $tag['style_key'],
                    'sort_order' => $tag['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
