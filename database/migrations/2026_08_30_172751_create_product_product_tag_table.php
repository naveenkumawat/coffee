<?php

use App\Enums\ProductTagStyle;
use App\Support\ProductMarketingTags;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_product_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_tag_id')->constrained('product_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'product_tag_id']);
        });

        $now = now();

        $defaults = [
            ['name' => 'New', 'slug' => ProductMarketingTags::NEW, 'style_key' => ProductTagStyle::Primary->value, 'sort_order' => 10],
            ['name' => 'Top Seller', 'slug' => ProductMarketingTags::TOP_SELLER, 'style_key' => ProductTagStyle::Accent->value, 'sort_order' => 20],
            ['name' => 'Featured', 'slug' => ProductMarketingTags::FEATURED, 'style_key' => ProductTagStyle::Soft->value, 'sort_order' => 30],
            ['name' => 'Seasonal', 'slug' => 'seasonal', 'style_key' => ProductTagStyle::Warning->value, 'sort_order' => 40],
            ['name' => 'Popular', 'slug' => 'popular', 'style_key' => ProductTagStyle::Accent->value, 'sort_order' => 50],
            ['name' => 'Limited', 'slug' => 'limited', 'style_key' => ProductTagStyle::Warning->value, 'sort_order' => 60],
            ['name' => 'Recommended', 'slug' => 'recommended', 'style_key' => ProductTagStyle::Muted->value, 'sort_order' => 70],
        ];

        foreach ($defaults as $tag) {
            DB::table('product_tags')->updateOrInsert(
                ['slug' => $tag['slug']],
                [
                    'name' => $tag['name'],
                    'style_key' => $tag['style_key'],
                    'sort_order' => $tag['sort_order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
            );
        }

        if (! Schema::hasTable('products')) {
            return;
        }

        $tagIds = DB::table('product_tags')->pluck('id', 'slug');

        DB::table('products')
            ->select(['id', 'is_new', 'is_bestseller', 'is_featured'])
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($tagIds, $now): void {
                $rows = [];

                foreach ($products as $product) {
                    $slugs = [];

                    if ((bool) $product->is_new) {
                        $slugs[] = ProductMarketingTags::NEW;
                    }

                    if ((bool) $product->is_bestseller) {
                        $slugs[] = ProductMarketingTags::TOP_SELLER;
                    }

                    if ((bool) $product->is_featured) {
                        $slugs[] = ProductMarketingTags::FEATURED;
                    }

                    foreach (array_unique($slugs) as $slug) {
                        $tagId = $tagIds[$slug] ?? null;

                        if ($tagId === null) {
                            continue;
                        }

                        $rows[] = [
                            'product_id' => $product->id,
                            'product_tag_id' => $tagId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('product_product_tag')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_product_tag');
    }
};
