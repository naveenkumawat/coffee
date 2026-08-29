<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('menu_categories') || ! DB::getSchemaBuilder()->hasTable('menu_items')) {
            return;
        }

        $categories = DB::table('menu_categories')->orderBy('id')->get();

        foreach ($categories as $category) {
            DB::table('product_categories')->updateOrInsert(
                ['slug' => $category->slug],
                [
                    'name' => $category->name,
                    'description' => $category->description,
                    'image_path' => null,
                    'sort_order' => $category->sort_order,
                    'is_active' => $category->is_active,
                    'deleted_at' => null,
                    'updated_at' => now(),
                    'created_at' => $category->created_at ?? now(),
                ],
            );
        }

        $products = DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
            ->select('menu_items.*', 'menu_categories.slug as category_slug')
            ->orderBy('menu_items.id')
            ->get();

        foreach ($products as $item) {
            $categoryId = DB::table('product_categories')->where('slug', $item->category_slug)->value('id');

            if (! $categoryId) {
                continue;
            }

            DB::table('products')->updateOrInsert(
                ['slug' => $item->slug],
                [
                    'product_category_id' => $categoryId,
                    'name' => $item->name,
                    'sku' => null,
                    'short_description' => $item->description,
                    'description' => $item->description,
                    'customer_ingredient_summary' => null,
                    'image_path' => null,
                    'preparation_time_minutes' => null,
                    'sort_order' => $item->sort_order,
                    'is_active' => true,
                    'is_available' => $item->is_available,
                    'is_featured' => $item->is_featured,
                    'deleted_at' => null,
                    'updated_at' => now(),
                    'created_at' => $item->created_at ?? now(),
                ],
            );

            $productId = DB::table('products')->where('slug', $item->slug)->value('id');

            if (! $productId) {
                continue;
            }

            DB::table('product_variants')->updateOrInsert(
                [
                    'product_id' => $productId,
                    'name' => 'Standard',
                ],
                [
                    'serving_size_value' => '1.000',
                    'serving_size_unit' => 'piece',
                    'price' => $item->price,
                    'sort_order' => 1,
                    'is_active' => true,
                    'is_available' => $item->is_available,
                    'deleted_at' => null,
                    'updated_at' => now(),
                    'created_at' => $item->created_at ?? now(),
                ],
            );
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('menu_categories') || ! DB::getSchemaBuilder()->hasTable('menu_items')) {
            return;
        }

        $menuItemSlugs = DB::table('menu_items')->pluck('slug')->all();
        $menuCategorySlugs = DB::table('menu_categories')->pluck('slug')->all();

        $productIds = DB::table('products')->whereIn('slug', $menuItemSlugs)->pluck('id')->all();

        if ($productIds !== []) {
            DB::table('product_variants')->whereIn('product_id', $productIds)->where('name', 'Standard')->delete();
            DB::table('product_flavour_product')->whereIn('product_id', $productIds)->delete();
            DB::table('products')->whereIn('id', $productIds)->delete();
        }

        $categoryIds = DB::table('product_categories')->whereIn('slug', $menuCategorySlugs)->pluck('id')->all();

        if ($categoryIds !== []) {
            DB::table('product_category_product_flavour')->whereIn('product_category_id', $categoryIds)->delete();
            DB::table('product_categories')->whereIn('id', $categoryIds)->delete();
        }
    }
};
