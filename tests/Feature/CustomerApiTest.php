<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProductServingUnit;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Notifications\CustomerResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_customer_api_routes_require_authentication(): void
    {
        $this->getJson(route('api.v1.customer.me'))
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated.',
                'errors' => [],
            ]);
    }

    public function test_customer_can_register_login_fetch_profile_update_password_and_logout_via_api(): void
    {
        $registerResponse = $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Nina Customer',
            'email' => 'nina@example.test',
            'phone' => '9999999999',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('data.email', 'nina@example.test')
            ->assertJsonPath('data.role', 'customer');

        $this->assertAuthenticated('web');

        $this->postJson(route('api.v1.auth.logout'))
            ->assertOk()
            ->assertJsonPath('message', 'Customer logout successful.');

        $customer = User::query()->where('email', 'nina@example.test')->firstOrFail();

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'nina@example.test',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.id', $customer->id);

        $this->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('data.email', 'nina@example.test');

        $this->putJson(route('api.v1.customer.profile.update'), [
            'name' => 'Nina Updated',
            'email' => 'nina.updated@example.test',
            'phone' => '8888888888',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nina Updated')
            ->assertJsonPath('data.email', 'nina.updated@example.test');

        $this->putJson(route('api.v1.customer.password.update'), [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpassword123', $customer->fresh()->password));

        $this->postJson(route('api.v1.auth.logout'))
            ->assertOk();
    }

    public function test_customer_password_reset_flow_is_available_via_api(): void
    {
        Notification::fake();

        $customer = User::factory()->customer()->create([
            'email' => 'reset.customer@example.test',
        ]);

        $this->postJson(route('api.v1.auth.password.forgot'), [
            'email' => $customer->email,
        ])->assertOk();

        Notification::assertSentTo($customer, CustomerResetPasswordNotification::class);

        $token = Password::broker('users')->createToken($customer);

        $this->postJson(route('api.v1.auth.password.reset'), [
            'email' => $customer->email,
            'token' => $token,
            'password' => 'resetpass123',
            'password_confirmation' => 'resetpass123',
        ])->assertOk()
            ->assertJsonPath('data.email', $customer->email);

        $this->assertTrue(Hash::check('resetpass123', $customer->fresh()->password));
        $this->assertAuthenticatedAs($customer, 'web');
    }

    public function test_catalog_endpoints_only_return_customer_safe_available_catalog_data(): void
    {
        [$category, $flavour, $product, $variant] = $this->createPublicCatalogProduct(
            price: '12.50',
            productName: 'Vanilla Latte',
            variantName: 'Regular',
            featured: true,
        );

        $hiddenCategory = ProductCategory::factory()->create([
            'name' => 'Hidden Drinks',
            'is_active' => true,
        ]);
        $hiddenProduct = Product::factory()->create([
            'product_category_id' => $hiddenCategory->id,
            'name' => 'Secret Mocha',
            'is_active' => true,
            'is_available' => false,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $hiddenProduct->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->getJson(route('api.v1.catalog.categories.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $category->name);

        $this->getJson(route('api.v1.catalog.flavours.index'))
            ->assertOk()
            ->assertJsonPath('data.0.name', $flavour->name)
            ->assertJsonPath('data.0.categories.0.name', $category->name);

        $this->getJson(route('api.v1.catalog.products.index', [
            'product_category_id' => $category->id,
            'product_flavour_id' => $flavour->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $product->name)
            ->assertJsonPath('data.0.variants.0.name', $variant->name)
            ->assertJsonMissingPath('data.0.sku')
            ->assertJsonMissingPath('data.0.variants.0.recipe');

        $this->getJson(route('api.v1.catalog.products.featured'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_featured', true);

        $product->update([
            'is_new' => true,
            'is_bestseller' => true,
            'is_vegetarian' => true,
            'is_customizable' => false,
        ]);

        $this->getJson(route('api.v1.catalog.products.index', ['new' => 'new']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_new', true)
            ->assertJsonPath('data.0.is_vegetarian', true)
            ->assertJsonPath('data.0.is_customizable', false);

        $this->getJson(route('api.v1.catalog.products.index', ['bestseller' => 'bestseller']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_bestseller', true);

        $this->getJson(route('api.v1.catalog.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.category.name', $category->name)
            ->assertJsonPath('data.default_variant.id', $variant->id)
            ->assertJsonPath('data.is_new', true)
            ->assertJsonPath('data.is_bestseller', true)
            ->assertJsonMissingPath('data.recipe')
            ->assertJsonMissingPath('data.internal_notes');

        $this->getJson(route('api.v1.catalog.variants.index', [
            'product_id' => $product->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', $product->name)
            ->assertJsonMissingPath('data.0.cost');
    }

    public function test_customer_cart_api_crud_count_and_totals_are_customer_scoped(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        [, , $product, $variant] = $this->createPublicCatalogProduct(
            price: '5.00',
            productName: 'Cold Brew',
            variantName: 'Bottle',
        );

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.product.name', $product->name)
            ->assertJsonPath('meta.summary.item_count', 2)
            ->assertJsonPath('meta.summary.total', '10.00');

        $variant->update(['price' => '5.75']);
        $cartItem = CartItem::query()->firstOrFail();

        $this->getJson(route('api.v1.cart.show'))
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', '5.75')
            ->assertJsonPath('data.items.0.line_total', '11.50')
            ->assertJsonPath('meta.summary.total', '11.50');

        $this->getJson(route('api.v1.cart.count'))
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->putJson(route('api.v1.cart.items.update', $cartItem), [
            'quantity' => 3,
        ])->assertOk()
            ->assertJsonPath('meta.summary.item_count', 3)
            ->assertJsonPath('meta.summary.total', '17.25');

        $otherCart = Cart::factory()->create(['customer_id' => $otherCustomer->id]);
        $otherItem = CartItem::factory()->create([
            'cart_id' => $otherCart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->deleteJson(route('api.v1.cart.items.destroy', $otherItem))
            ->assertForbidden();

        $this->deleteJson(route('api.v1.cart.items.destroy', $cartItem))
            ->assertOk()
            ->assertJsonPath('meta.summary.item_count', 0);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->deleteJson(route('api.v1.cart.clear'))
            ->assertOk()
            ->assertJsonPath('meta.summary.item_count', 0)
            ->assertJsonPath('meta.summary.total', '0.00');
    }

    public function test_checkout_summary_rejects_empty_or_unavailable_carts(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'web');

        $this->getJson(route('api.v1.checkout.summary'))
            ->assertStatus(422)
            ->assertJsonPath('errors.cart.0', 'Your cart is empty. Add a few items before checkout.');

        [, , , $variant] = $this->createPublicCatalogProduct(price: '6.50');

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $variant->update(['is_available' => false]);

        $this->getJson(route('api.v1.checkout.summary'))
            ->assertStatus(422)
            ->assertJsonPath('errors.cart.0', 'Please remove unavailable items before checkout.');
    }

    public function test_checkout_submit_creates_pending_payment_order_with_server_recalculated_totals_and_idempotency(): void
    {
        config()->set('coffee.payments.display_name', 'UPI Transfer');
        config()->set('coffee.payments.instructions', 'Pay the total via UPI and send the screenshot on WhatsApp.');
        config()->set('coffee.payments.upi_id', 'coffee@upi');
        config()->set('coffee.payments.whatsapp_number', '+919999999999');

        $customer = User::factory()->customer()->create([
            'name' => 'Riya Customer',
            'email' => 'riya@example.test',
            'phone' => '7777777777',
        ]);
        [, , , $variant] = $this->createPublicCatalogProduct(
            price: '10.00',
            productName: 'Hazelnut Latte',
            variantName: 'Regular',
        );

        $this->actingAs($customer, 'web');

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $summaryResponse = $this->getJson(route('api.v1.checkout.summary'))
            ->assertOk()
            ->assertJsonPath('meta.summary.total', '20.00');

        $checkoutToken = (string) $summaryResponse->json('meta.checkout_token');
        $variant->update(['price' => '12.50']);

        $payload = [
            'checkout_token' => $checkoutToken,
            'fulfilment_method' => 'takeaway',
            'customer_name' => 'Riya Customer',
            'customer_email' => 'riya@example.test',
            'customer_phone' => '7777777777',
            'pickup_name' => 'Riya Pickup',
            'pickup_phone' => '6666666666',
            'customer_notes' => 'Less sugar',
            'pickup_notes' => 'Call on arrival',
        ];

        $this->postJson(route('api.v1.checkout.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('data.total_amount', '25.00')
            ->assertJsonPath('data.items.0.unit_price', '12.50')
            ->assertJsonPath('meta.payment.upi_id', 'coffee@upi')
            ->assertJsonPath('meta.payment.whatsapp_number', '+919999999999');

        $order = Order::query()->with(['items', 'statusHistory'])->firstOrFail();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('25.00', $order->total_amount);
        $this->assertSame(0, $customer->cart()->firstOrFail()->items()->count());
        $this->assertCount(1, $order->items);
        $this->assertCount(1, $order->statusHistory);
        $this->assertNull($order->payment_confirmed_at);

        $this->postJson(route('api.v1.checkout.store'), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Order already exists for this checkout token.')
            ->assertJsonPath('data.order_number', $order->order_number);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_customer_order_api_is_owner_only_and_excludes_internal_fields(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $manager = User::factory()->manager()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => 'Pickup Name',
            'pickup_phone' => '9999999999',
            'assigned_barista_id' => $manager->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        $recipe = Recipe::factory()->create();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'recipe_id' => $recipe->id,
            'product_name' => 'Mocha',
            'variant_name' => 'Regular',
            'unit_price' => '8.50',
            'quantity' => 2,
            'line_subtotal' => '17.00',
        ]);

        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'changed_by' => $manager->id,
            'notes' => 'Internal status note',
            'to_status' => OrderStatus::PendingPayment,
        ]);

        config()->set('coffee.payments.display_name', 'UPI Transfer');
        config()->set('coffee.payments.instructions', 'Pay via UPI and send a screenshot on WhatsApp.');
        config()->set('coffee.payments.upi_id', 'coffee@upi');
        config()->set('coffee.payments.whatsapp_number', '+919999999999');

        $this->actingAs($customer, 'web');

        $this->getJson(route('api.v1.orders.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $order->order_number);

        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.items.0.product_name', 'Mocha')
            ->assertJsonPath('data.status_timeline.0.to_status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('meta.payment.display_name', 'UPI Transfer')
            ->assertJsonPath('meta.payment.upi_id', 'coffee@upi')
            ->assertJsonPath('meta.payment.whatsapp_number', '+919999999999')
            ->assertJsonMissingPath('data.assigned_barista_id')
            ->assertJsonMissingPath('data.items.0.recipe_id')
            ->assertJsonMissingPath('data.status_timeline.0.changed_by')
            ->assertJsonMissingPath('data.status_timeline.0.notes');

        Sanctum::actingAs($otherCustomer);

        $this->getJson(route('api.v1.orders.show', $order))
            ->assertForbidden();
    }

    public function test_api_returns_json_validation_and_not_found_errors(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'web');

        $this->postJson(route('api.v1.cart.items.store'), [
            'quantity' => 0,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.product_variant_id.0', 'The product variant id field is required.');

        $this->getJson('/api/v1/orders/999999')
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Resource not found.',
                'errors' => [],
            ]);
    }

    protected function createPublicCatalogProduct(
        string $price,
        string $productName = 'House Latte',
        string $variantName = 'Regular',
        bool $featured = false,
    ): array {
        $category = ProductCategory::factory()->create([
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ]);

        $flavour = ProductFlavour::factory()->create([
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ]);
        $flavour->categories()->sync([$category->id]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $productName,
            'short_description' => 'Freshly brewed for the public menu.',
            'description' => 'Made for customer-facing ordering.',
            'customer_ingredient_summary' => 'Coffee, milk',
            'is_active' => true,
            'is_available' => true,
            'is_featured' => $featured,
        ]);
        $product->flavours()->sync([$flavour->id]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => $variantName,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);

        return [$category, $flavour, $product->fresh(['category', 'flavours', 'defaultVariant', 'variants']), $variant->fresh(['product.category'])];
    }
}
