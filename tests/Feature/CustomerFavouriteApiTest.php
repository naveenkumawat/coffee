<?php

namespace Tests\Feature;

use App\Enums\ProductServingUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFavourite;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFavouriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_favourites_require_customer_authentication(): void
    {
        $this->getJson(route('api.v1.favourites.index'))
            ->assertUnauthorized();

        $this->postJson(route('api.v1.favourites.store'), [
            'product_id' => 1,
        ])->assertUnauthorized();
    }

    public function test_customer_can_add_list_and_remove_favourites_without_duplicates(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Favourite Latte');
        $hiddenProduct = $this->makePublicProduct('Hidden Brew');
        $hiddenProduct->update(['is_available' => false]);

        $this->actingAs($customer, 'web');

        $this->postJson(route('api.v1.favourites.store'), [
            'product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Favourite Latte')
            ->assertJsonMissingPath('data.production_cost');

        $this->postJson(route('api.v1.favourites.store'), [
            'product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('data.id', $product->id);

        $this->assertDatabaseCount('product_favourites', 1);

        $this->postJson(route('api.v1.favourites.store'), [
            'product_id' => $hiddenProduct->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);

        $this->getJson(route('api.v1.favourites.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);

        $this->getJson(route('api.v1.favourites.ids'))
            ->assertOk()
            ->assertJsonPath('data.ids.0', $product->id);

        ProductFavourite::query()->create([
            'customer_id' => $otherCustomer->id,
            'product_id' => $product->id,
        ]);

        $this->getJson(route('api.v1.favourites.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson(route('api.v1.favourites.destroy', $product))
            ->assertOk()
            ->assertJsonPath('message', 'Product removed from favourites.');

        $this->assertDatabaseMissing('product_favourites', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $this->assertDatabaseHas('product_favourites', [
            'customer_id' => $otherCustomer->id,
            'product_id' => $product->id,
        ]);

        $this->getJson(route('api.v1.favourites.ids'))
            ->assertOk()
            ->assertJsonPath('data.ids', []);
    }

    protected function makePublicProduct(string $name): Product
    {
        $category = ProductCategory::factory()->create([
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
            'is_featured' => false,
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

        return $product->fresh(['category', 'defaultVariant', 'variants']);
    }
}
