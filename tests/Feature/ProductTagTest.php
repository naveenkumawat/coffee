<?php

namespace Tests\Feature;

use App\Enums\ProductTagStyle;
use App\Models\Product;
use App\Models\ProductTag;
use App\Models\User;
use App\Support\ProductMarketingTags;
use Database\Seeders\ProductTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_marketing_tags_are_seeded_and_mirrored_from_product_flags(): void
    {
        $this->seed(ProductTagSeeder::class);

        $this->assertDatabaseHas('product_tags', [
            'slug' => ProductMarketingTags::NEW,
            'name' => 'New',
        ]);

        $product = Product::factory()->create([
            'is_new' => true,
            'is_bestseller' => true,
            'is_featured' => false,
        ]);

        $tagIds = ProductTag::query()
            ->whereIn('slug', [ProductMarketingTags::NEW, ProductMarketingTags::TOP_SELLER])
            ->pluck('id')
            ->all();

        $product->tags()->sync($tagIds);

        $this->assertTrue($product->fresh()->tags->contains(fn (ProductTag $tag): bool => $tag->slug === ProductMarketingTags::NEW));
        $this->assertTrue($product->fresh()->tags->contains(fn (ProductTag $tag): bool => $tag->slug === ProductMarketingTags::TOP_SELLER));
    }

    public function test_manager_can_create_a_product_tag(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.products.tags.store'), [
            'name' => 'Seasonal',
            'style_key' => ProductTagStyle::Warning->value,
            'sort_order' => 40,
            'is_active' => 1,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('product_tags', [
            'slug' => 'seasonal',
            'name' => 'Seasonal',
            'style_key' => ProductTagStyle::Warning->value,
        ]);
    }

    public function test_catalog_product_payload_exposes_normalized_tags(): void
    {
        $this->seed(ProductTagSeeder::class);

        $product = Product::factory()->create([
            'is_active' => true,
            'is_available' => true,
            'is_new' => true,
        ]);

        $newTag = ProductTag::query()->where('slug', ProductMarketingTags::NEW)->firstOrFail();
        $product->tags()->sync([$newTag->id]);

        $this->getJson(route('api.v1.catalog.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.tags.0.key', 'new')
            ->assertJsonPath('data.tags.0.label', 'New')
            ->assertJsonPath('data.tags.0.style', ProductTagStyle::Primary->value)
            ->assertJsonMissingPath('data.tags.0.id');
    }
}
