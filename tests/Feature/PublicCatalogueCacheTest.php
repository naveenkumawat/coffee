<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\ProductVariant;
use App\Services\Product\ProductCatalogService;
use App\Services\Product\ProductCatalogServiceInterface;
use App\Services\Product\ProductServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicCatalogueCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_products_index_returns_full_active_catalogue_without_pagination(): void
    {
        $categoryA = ProductCategory::factory()->create(['name' => 'Alpha', 'sort_order' => 1, 'is_active' => true]);
        $categoryB = ProductCategory::factory()->create(['name' => 'Beta', 'sort_order' => 2, 'is_active' => true]);

        $first = $this->makeVisibleProduct($categoryA, 'Latte A', sortOrder: 2);
        $second = $this->makeVisibleProduct($categoryA, 'Affogato A', sortOrder: 1);
        $third = $this->makeVisibleProduct($categoryB, 'Mocha B', sortOrder: 1);

        $this->makeVisibleProduct($categoryA, 'Hidden Inactive', isActive: false);
        $this->makeVisibleProduct($categoryB, 'Hidden Unavailable', isAvailable: false);

        $response = $this->getJson(route('api.v1.catalog.products.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('meta.pagination')
            ->assertHeader('ETag')
            ->assertHeader('Last-Modified')
            ->assertHeader('Cache-Control');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Affogato A', 'Latte A', 'Mocha B'], $names);

        $response->assertJsonMissingPath('data.0.variants.0.recipe')
            ->assertJsonMissingPath('data.0.variants.0.cost')
            ->assertJsonMissingPath('data.0.margin')
            ->assertJsonMissingPath('data.0.sku');

        $etag = $response->headers->get('ETag');

        $this->getJson(route('api.v1.catalog.products.index'), [
            'If-None-Match' => $etag,
        ])->assertStatus(304);

        // Ensure ordering stays category sort then product sort.
        $this->assertSame($categoryA->id, $response->json('data.0.category.id'));
        $this->assertSame($first->id, $response->json('data.1.id'));
        $this->assertSame($third->id, $response->json('data.2.id'));
        $this->assertSame($second->id, $response->json('data.0.id'));
    }

    public function test_public_catalogue_cache_invalidates_after_product_mutation(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = $this->makeVisibleProduct($category, 'Cached Latte');

        $this->getJson(route('api.v1.catalog.products.index'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Cached Latte']);

        $this->assertTrue(Cache::has(ProductCatalogService::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY));

        app(ProductServiceInterface::class)->delete($product);

        $this->assertFalse(Cache::has(ProductCatalogService::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY));

        $this->getJson(route('api.v1.catalog.products.index'))
            ->assertOk()
            ->assertJsonMissing(['name' => 'Cached Latte']);
    }

    public function test_server_side_filters_still_work_against_cached_catalogue(): void
    {
        $hot = ProductCategory::factory()->create(['name' => 'Hot', 'is_active' => true]);
        $cold = ProductCategory::factory()->create(['name' => 'Cold', 'is_active' => true]);
        $mocha = ProductFlavour::factory()->create(['name' => 'Mocha', 'is_active' => true]);
        $vanilla = ProductFlavour::factory()->create(['name' => 'Vanilla', 'is_active' => true]);

        $hotProduct = $this->makeVisibleProduct($hot, 'Hot Mocha', isNew: true);
        $hotProduct->flavours()->sync([$mocha->id]);

        $coldProduct = $this->makeVisibleProduct($cold, 'Iced Vanilla', isBestseller: true);
        $coldProduct->flavours()->sync([$vanilla->id]);

        $this->getJson(route('api.v1.catalog.products.index', [
            'product_category_ids' => [$hot->id],
            'product_flavour_ids' => [$mocha->id],
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Hot Mocha')
            ->assertJsonMissingPath('meta.pagination');

        $this->getJson(route('api.v1.catalog.products.index', ['new' => 'new']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Hot Mocha');

        $this->getJson(route('api.v1.catalog.products.index', ['bestseller' => 'bestseller']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Iced Vanilla');

        app(ProductCatalogServiceInterface::class)->flushPublicCache();
        $this->assertFalse(Cache::has(ProductCatalogService::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY));
    }

    protected function makeVisibleProduct(
        ProductCategory $category,
        string $name,
        int $sortOrder = 1,
        bool $isActive = true,
        bool $isAvailable = true,
        bool $isNew = false,
        bool $isBestseller = false,
    ): Product {
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'is_available' => $isAvailable,
            'is_featured' => false,
            'is_new' => $isNew,
            'is_bestseller' => $isBestseller,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '5.00',
            'is_active' => true,
            'is_available' => true,
        ]);

        return $product->fresh(['variants']);
    }
}
