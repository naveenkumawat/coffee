<?php

namespace Tests\Feature;

use App\Enums\ProductServingUnit;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\ProductTag;
use App\Models\User;
use App\Services\Product\ProductCatalogService;
use Database\Seeders\ProductTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_product_category_with_auto_slug(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.products.categories.store'), [
            'name' => 'Seasonal Drinks',
            'description' => 'Limited-time launches.',
            'sort_order' => 5,
            'is_active' => 1,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('product_categories', [
            'name' => 'Seasonal Drinks',
            'slug' => 'seasonal-drinks',
        ]);
    }

    public function test_product_category_slug_is_made_unique(): void
    {
        $manager = User::factory()->manager()->create();
        ProductCategory::factory()->create([
            'name' => 'Cold Brew',
            'slug' => 'cold-brew',
        ]);

        $this->actingAs($manager, 'admin')->post(route('administrator.products.categories.store'), [
            'name' => 'Cold Brew',
            'description' => 'Second category',
        ])->assertRedirect();

        $this->assertDatabaseHas('product_categories', [
            'slug' => 'cold-brew-2',
        ]);
    }

    public function test_manager_can_create_flavour_with_category_applicability(): void
    {
        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.products.flavours.store'), [
            'name' => 'Toffee Nut',
            'description' => 'Sweet nutty finish',
            'product_category_ids' => [$category->id],
            'is_active' => 1,
        ]);

        $response->assertRedirect();

        $flavour = ProductFlavour::query()->where('slug', 'toffee-nut')->firstOrFail();

        $this->assertTrue($flavour->categories->contains($category));
    }

    public function test_manager_can_create_product_with_variants_and_flavours(): void
    {
        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();
        $flavour = ProductFlavour::factory()->create();
        $this->seed(ProductTagSeeder::class);

        $tagIds = ProductTag::query()
            ->whereIn('slug', ['new', 'top-seller', 'featured'])
            ->pluck('id')
            ->all();

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Citrus Espresso Tonic',
            'sku' => 'TONIC-001',
            'short_description' => 'Espresso with citrus tonic.',
            'description' => 'A sparkling espresso signature.',
            'customer_ingredient_summary' => 'Espresso, tonic, citrus',
            'image_path' => 'products/tonic.png',
            'preparation_time_minutes' => 4,
            'sort_order' => 1,
            'product_flavour_ids' => [$flavour->id],
            'product_tag_ids' => $tagIds,
            'is_active' => 0,
            'is_available' => 1,
            'is_vegetarian' => 1,
            'is_customizable' => 1,
            'variants' => [
                [
                    'name' => 'Regular',
                    'serving_size_value' => '300.000',
                    'serving_size_unit' => ProductServingUnit::Milliliter->value,
                    'price' => '5.25',
                    'sort_order' => 1,
                    'is_active' => 1,
                    'is_available' => 1,
                ],
                [
                    'name' => 'Large',
                    'serving_size_value' => '450.000',
                    'serving_size_unit' => ProductServingUnit::Milliliter->value,
                    'price' => '6.25',
                    'sort_order' => 2,
                    'is_active' => 1,
                    'is_available' => 1,
                ],
            ],
        ]);

        $response->assertRedirect();

        $product = Product::query()->where('slug', 'citrus-espresso-tonic')->firstOrFail();

        $this->assertSame('TONIC-001', $product->sku);
        $this->assertFalse($product->is_active);
        $this->assertTrue($product->is_new);
        $this->assertTrue($product->is_bestseller);
        $this->assertTrue($product->is_featured);
        $this->assertTrue($product->is_vegetarian);
        $this->assertTrue($product->is_customizable);
        $this->assertCount(2, $product->variants);
        $this->assertTrue($product->flavours->contains($flavour));
        $this->assertCount(3, $product->tags);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'name' => 'Large',
            'price' => '6.25',
        ]);
    }

    public function test_manager_can_filter_products_by_search_and_flavour(): void
    {
        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create(['name' => 'Cold Coffee']);
        $vanilla = ProductFlavour::factory()->create(['name' => 'Vanilla']);
        $hazelnut = ProductFlavour::factory()->create(['name' => 'Hazelnut']);

        $visibleProduct = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Iced Vanilla Latte',
            'sku' => 'IVL-1',
        ]);
        $visibleProduct->flavours()->sync([$vanilla->id]);

        $hiddenProduct = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Hazelnut Cold Brew',
            'sku' => 'HCB-1',
        ]);
        $hiddenProduct->flavours()->sync([$hazelnut->id]);

        $response = $this->actingAs($manager, 'admin')->get(route('administrator.products.index', [
            'search' => 'latte',
            'product_flavour_id' => $vanilla->id,
        ]));

        $response
            ->assertOk()
            ->assertSee('Iced Vanilla Latte')
            ->assertDontSee('Hazelnut Cold Brew');
    }

    public function test_barista_can_view_products_but_cannot_manage_products(): void
    {
        $barista = User::factory()->create([
            'role' => UserRole::Barista,
        ]);

        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->actingAs($barista, 'admin')
            ->get(route('barista.products.index'))
            ->assertOk()
            ->assertSee($product->name);

        $this->actingAs($barista, 'admin')
            ->post(route('administrator.products.categories.store'), [
                'name' => 'Blocked Category',
            ])
            ->assertForbidden();
    }

    public function test_cannot_archive_referenced_product_category_or_flavour(): void
    {
        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();
        $flavour = ProductFlavour::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
        ]);
        $product->flavours()->sync([$flavour->id]);

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.products.categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.products.flavours.destroy', $flavour))
            ->assertSessionHasErrors('flavour');
    }

    public function test_archiving_product_soft_deletes_variants_and_flushes_catalog_cache(): void
    {
        $manager = User::factory()->manager()->create();
        $product = Product::factory()->create();
        $variant = $product->variants()->create([
            'name' => 'Regular',
            'serving_size_value' => '250.000',
            'serving_size_unit' => ProductServingUnit::Milliliter->value,
            'price' => '4.50',
            'sort_order' => 1,
            'is_active' => true,
            'is_available' => true,
        ]);

        Cache::put(ProductCatalogService::PUBLIC_PRODUCT_CACHE_KEY, ['cached'], now()->addMinutes(10));
        Cache::put(ProductCatalogService::FEATURED_PRODUCT_CACHE_KEY, ['cached'], now()->addMinutes(10));

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.products.destroy', $product))
            ->assertRedirect(route('administrator.products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
        $this->assertFalse(Cache::has(ProductCatalogService::PUBLIC_PRODUCT_CACHE_KEY));
        $this->assertFalse(Cache::has(ProductCatalogService::FEATURED_PRODUCT_CACHE_KEY));
    }

    public function test_product_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ProductCategorySeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ProductFlavourSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ProductSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ProductCategorySeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ProductFlavourSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ProductSeeder']);

        $this->assertSame(7, ProductCategory::query()->count());
        $this->assertSame(10, ProductFlavour::query()->count());
        $this->assertSame(38, Product::query()->count());
    }
}
