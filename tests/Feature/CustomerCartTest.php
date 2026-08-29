<?php

namespace Tests\Feature;

use App\Enums\ProductServingUnit;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Parsers\Cart\CartParser;
use App\Parsers\Cart\CartParserInterface;
use App\Repositories\Cart\CartRepository;
use App\Repositories\Cart\CartRepositoryInterface;
use App\Services\Cart\CartService;
use App\Services\Cart\CartServiceInterface;
use App\Transfers\Cart\CartItemTransfer;
use App\Transfers\Cart\CartItemTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_architecture_bindings_and_schema_exist(): void
    {
        $this->assertInstanceOf(CartRepository::class, $this->app->make(CartRepositoryInterface::class));
        $this->assertInstanceOf(CartService::class, $this->app->make(CartServiceInterface::class));
        $this->assertInstanceOf(CartParser::class, $this->app->make(CartParserInterface::class));
        $this->assertInstanceOf(CartItemTransfer::class, $this->app->make(CartItemTransferInterface::class));
        $this->assertTrue(Schema::hasTable('carts'));
        $this->assertTrue(Schema::hasTable('cart_items'));
        $this->assertTrue(Schema::hasColumn('carts', 'customer_id'));
        $this->assertTrue(Schema::hasColumn('cart_items', 'product_variant_id'));
    }

    public function test_customer_can_add_items_and_duplicate_variants_merge_quantities(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '4.75');

        $this->actingAs($customer, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('customer.cart.show'));

        $this->actingAs($customer, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ])
            ->assertRedirect(route('customer.cart.show'));

        $cart = Cart::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
    }

    public function test_customer_can_update_remove_and_clear_cart_items(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '5.50');
        $cart = Cart::factory()->create(['customer_id' => $customer->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->actingAs($customer, 'web')
            ->put(route('customer.cart.items.update', $cartItem), [
                'quantity' => 4,
            ])
            ->assertRedirect(route('customer.cart.show'));

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 4,
        ]);

        $this->actingAs($customer, 'web')
            ->delete(route('customer.cart.items.destroy', $cartItem))
            ->assertRedirect(route('customer.cart.show'));

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->makePurchasableVariant(price: '3.95')->id,
            'quantity' => 2,
        ]);

        $this->actingAs($customer, 'web')
            ->delete(route('customer.cart.clear'))
            ->assertRedirect(route('customer.cart.show'));

        $this->assertSame(0, $cart->fresh()->items()->count());
    }

    public function test_cart_totals_use_current_variant_price_with_decimal_precision(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '4.75');

        $this->actingAs($customer, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 3,
            ]);

        $variant->update(['price' => '4.95']);

        $this->actingAs($customer, 'web')
            ->get(route('customer.cart.show'))
            ->assertOk()
            ->assertSee('$4.95')
            ->assertSee('$14.85')
            ->assertSee('Checkout coming next');
    }

    public function test_inactive_or_unavailable_products_and_variants_are_rejected(): void
    {
        $customer = User::factory()->customer()->create();
        $inactiveVariant = $this->makePurchasableVariant(price: '4.50');
        $inactiveVariant->update(['is_active' => false]);

        $this->actingAs($customer, 'web')
            ->from(route('home'))
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $inactiveVariant->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('product_variant_id');

        $unavailableProductVariant = $this->makePurchasableVariant(price: '5.25');
        $unavailableProductVariant->product->update(['is_available' => false]);

        $this->actingAs($customer, 'web')
            ->from(route('home'))
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $unavailableProductVariant->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('product_variant_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_is_isolated_per_customer_and_guests_are_redirected(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $cart = Cart::factory()->create(['customer_id' => $customer->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->makePurchasableVariant(price: '6.25')->id,
            'quantity' => 2,
        ]);

        $this->get(route('customer.cart.show'))->assertRedirect(route('customer.login'));

        $this->actingAs($otherCustomer, 'web')
            ->put(route('customer.cart.items.update', $cartItem), [
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($otherCustomer, 'web')
            ->delete(route('customer.cart.items.destroy', $cartItem))
            ->assertForbidden();
    }

    public function test_cart_count_appears_in_customer_navigation_and_internal_data_stays_hidden(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '7.10', productName: 'Cold Brew');

        $this->actingAs($customer, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 3,
            ]);

        $this->actingAs($customer, 'web')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Cart (3)')
            ->assertSee('Open my cart');

        $this->actingAs($customer, 'web')
            ->get(route('customer.cart.show'))
            ->assertOk()
            ->assertSee('Cold Brew')
            ->assertDontSee('Production Cost')
            ->assertDontSee('Margin')
            ->assertDontSee('Recipe');
    }

    public function test_internal_roles_cannot_use_customer_cart_routes(): void
    {
        $administrator = User::factory()->manager()->create();
        $variant = $this->makePurchasableVariant(price: '8.25');

        $this->actingAs($administrator, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertForbidden();
    }

    protected function makePurchasableVariant(
        string $price,
        string $productName = 'House Latte',
        string $variantName = 'Regular',
    ): ProductVariant {
        $category = ProductCategory::factory()->create([
            'name' => fake()->unique()->words(2, true),
        ]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $productName,
            'short_description' => 'Small-batch coffee with milk.',
            'description' => 'Freshly prepared for the public catalog.',
            'customer_ingredient_summary' => 'Coffee, milk',
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => $variantName,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);
    }
}
