<?php

namespace Tests\Feature;

use App\Enums\ProductServingUnit;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_api_returns_active_ordered_sections_and_products(): void
    {
        $first = $this->makePublicProduct('First Drink');
        $second = $this->makePublicProduct('Second Drink');
        $hidden = $this->makePublicProduct('Hidden Drink');
        $hidden->update(['is_available' => false]);
        $shared = $this->makePublicProduct('Shared Drink');

        $inactive = HomeSection::factory()->inactive()->create([
            'title' => 'Inactive Rail',
            'slug' => 'inactive-rail',
            'sort_order' => 1,
        ]);
        $inactive->products()->attach($first->id, ['sort_order' => 10]);

        $empty = HomeSection::factory()->create([
            'title' => 'Empty Rail',
            'slug' => 'empty-rail',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $later = HomeSection::factory()->create([
            'title' => 'Later Rail',
            'slug' => 'later-rail',
            'subtitle' => 'Later subtitle',
            'sort_order' => 30,
            'is_active' => true,
            'max_items' => 1,
        ]);
        $later->products()->attach([
            $second->id => ['sort_order' => 10],
            $shared->id => ['sort_order' => 20],
        ]);

        $earlier = HomeSection::factory()->create([
            'title' => 'Earlier Rail',
            'slug' => 'earlier-rail',
            'subtitle' => 'Earlier subtitle',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $earlier->products()->attach([
            $shared->id => ['sort_order' => 10],
            $first->id => ['sort_order' => 20],
            $hidden->id => ['sort_order' => 30],
        ]);

        $response = $this->getJson(route('api.v1.home.show'))
            ->assertOk()
            ->assertJsonPath('data.sections.0.title', 'Earlier Rail')
            ->assertJsonPath('data.sections.0.products.0.name', 'Shared Drink')
            ->assertJsonPath('data.sections.0.products.1.name', 'First Drink')
            ->assertJsonPath('data.sections.1.title', 'Later Rail')
            ->assertJsonPath('data.sections.1.products.0.name', 'Second Drink')
            ->assertJsonMissingPath('data.sections.2');

        $firstSectionProductNames = collect($response->json('data.sections.0.products'))->pluck('name')->all();
        $this->assertNotContains('Hidden Drink', $firstSectionProductNames);
        $this->assertCount(1, $response->json('data.sections.1.products'));
        $this->assertSame($empty->id, $empty->id);
    }

    protected function makePublicProduct(string $name): Product
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '250',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => '9.50',
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        return $product->fresh();
    }
}
