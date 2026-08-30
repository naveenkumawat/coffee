<?php

namespace Tests\Feature;

use App\Enums\ProductServingUnit;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestCartMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_mutate_server_cart_without_authentication(): void
    {
        $variant = $this->makePurchasableVariant();

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertUnauthorized();

        $this->getJson(route('api.v1.cart.show'))->assertUnauthorized();
        $this->getJson(route('api.v1.checkout.summary'))->assertUnauthorized();
    }

    public function test_authenticated_customer_can_merge_guest_cart_items_and_combine_duplicate_variants(): void
    {
        $customer = User::factory()->customer()->create();
        $variantA = $this->makePurchasableVariant(price: '4.50', productName: 'Latte A');
        $variantB = $this->makePurchasableVariant(price: '5.25', productName: 'Latte B', variantName: 'Large');

        $existingCart = Cart::factory()->create(['customer_id' => $customer->id]);
        CartItem::factory()->create([
            'cart_id' => $existingCart->id,
            'product_variant_id' => $variantA->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.merge'), [
            'items' => [
                ['product_variant_id' => $variantA->id, 'quantity' => 2],
                ['product_variant_id' => $variantA->id, 'quantity' => 1],
                ['product_variant_id' => $variantB->id, 'quantity' => 1],
            ],
            'idempotency_key' => 'merge-key-1',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Guest cart merged.')
            ->assertJsonPath('meta.summary.item_count', 5);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $existingCart->id,
            'product_variant_id' => $variantA->id,
            'quantity' => 4,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $existingCart->id,
            'product_variant_id' => $variantB->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_guest_cart_merge_is_idempotent_for_the_same_key(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant();

        Sanctum::actingAs($customer);

        $payload = [
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2],
            ],
            'idempotency_key' => 'retry-safe-merge',
        ];

        $this->postJson(route('api.v1.cart.merge'), $payload)
            ->assertOk()
            ->assertJsonPath('meta.summary.item_count', 2);

        $this->postJson(route('api.v1.cart.merge'), $payload)
            ->assertOk()
            ->assertJsonPath('meta.summary.item_count', 2);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
        $this->assertTrue(Cache::has(sprintf('cart-merge:%d:retry-safe-merge', $customer->id)));
    }

    protected function makePurchasableVariant(
        string $price = '4.75',
        string $productName = 'House Latte',
        string $variantName = 'Regular',
    ): ProductVariant {
        $category = ProductCategory::factory()->create([
            'name' => fake()->unique()->words(2, true),
        ]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $productName,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => $variantName,
            'price' => $price,
            'serving_size_value' => '250',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'is_active' => true,
            'is_available' => true,
        ]);
    }
}
