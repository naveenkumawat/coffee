<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProductServingUnit;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Parsers\Checkout\CheckoutParser;
use App\Parsers\Checkout\CheckoutParserInterface;
use App\Services\Checkout\CheckoutService;
use App\Services\Checkout\CheckoutServiceInterface;
use App\Services\Order\OrderServiceInterface;
use App\Transfers\Checkout\CheckoutTransfer;
use App\Transfers\Checkout\CheckoutTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CustomerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_architecture_bindings_and_schema_exist(): void
    {
        $this->assertInstanceOf(CheckoutService::class, $this->app->make(CheckoutServiceInterface::class));
        $this->assertInstanceOf(CheckoutParser::class, $this->app->make(CheckoutParserInterface::class));
        $this->assertInstanceOf(CheckoutTransfer::class, $this->app->make(CheckoutTransferInterface::class));
        $this->assertTrue(Schema::hasColumn('orders', 'checkout_token'));
        $this->assertTrue(Schema::hasColumn('orders', 'customer_name'));
        $this->assertTrue(Schema::hasColumn('orders', 'pickup_name'));
    }

    public function test_checkout_requires_customer_authentication(): void
    {
        $this->get(route('customer.checkout.show'))->assertRedirect(route('customer.login'));
        $this->post(route('customer.checkout.store'), [])->assertRedirect(route('customer.login'));
    }

    public function test_empty_cart_is_rejected(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'web')
            ->get(route('customer.checkout.show'))
            ->assertRedirect(route('customer.cart.show'))
            ->assertSessionHasErrors('cart');
    }

    public function test_unavailable_cart_items_block_checkout(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '9.50');
        $cart = Cart::factory()->create(['customer_id' => $customer->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $variant->update(['is_available' => false]);

        $this->actingAs($customer, 'web')
            ->get(route('customer.checkout.show'))
            ->assertRedirect(route('customer.cart.show'))
            ->assertSessionHasErrors('cart');
    }

    public function test_checkout_recalculates_prices_server_side_creates_pending_payment_order_and_clears_cart(): void
    {
        config()->set('coffee.payments.instructions', 'Pay via UPI and send a screenshot on WhatsApp.');
        config()->set('coffee.payments.upi_id', 'coffee@upi');
        config()->set('coffee.payments.whatsapp_number', '+919999999999');

        $customer = User::factory()->customer()->create([
            'name' => 'Nina Customer',
            'email' => 'nina@example.test',
            'phone' => '9999999999',
        ]);
        $variant = $this->makePurchasableVariant(price: '10.00', productName: 'Vanilla Latte', variantName: 'Regular');

        $this->actingAs($customer, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ]);

        $variant->update(['price' => '12.50']);

        $checkoutPage = $this->actingAs($customer, 'web')
            ->get(route('customer.checkout.show'))
            ->assertOk()
            ->assertSee('Review and confirm');

        $checkoutToken = (string) session(config('coffee.checkout.session_token_key'));

        $response = $this->actingAs($customer, 'web')
            ->post(route('customer.checkout.store'), [
                'checkout_token' => $checkoutToken,
                'fulfilment_method' => 'takeaway',
                'customer_name' => 'Nina Customer',
                'customer_email' => 'nina@example.test',
                'customer_phone' => '9999999999',
                'pickup_name' => 'Nina Pickup',
                'pickup_phone' => '8888888888',
                'customer_notes' => 'Less sweet',
                'pickup_notes' => 'Call on arrival',
            ]);

        $order = Order::query()->with(['items', 'statusHistory'])->firstOrFail();

        $response
            ->assertRedirect(route('customer.checkout.confirmation', $order));

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('takeaway', $order->fulfilment_method?->value);
        $this->assertSame('25.00', $order->total_amount);
        $this->assertSame('Nina Customer', $order->customer_name);
        $this->assertSame('nina@example.test', $order->customer_email);
        $this->assertSame('9999999999', $order->customer_phone);
        $this->assertSame('Nina Pickup', $order->pickup_name);
        $this->assertSame('8888888888', $order->pickup_phone);
        $this->assertSame('Call on arrival', $order->pickup_notes);
        $this->assertNull($order->payment_confirmed_at);
        $this->assertCount(1, $order->items);
        $this->assertSame('Vanilla Latte', $order->items->first()->product_name);
        $this->assertSame('Regular', $order->items->first()->variant_name);
        $this->assertSame('12.50', $order->items->first()->unit_price);
        $this->assertSame('25.00', $order->items->first()->line_subtotal);
        $this->assertDatabaseCount('order_status_histories', 1);
        $this->assertSame(0, $customer->cart()->firstOrFail()->items()->count());

        $this->actingAs($customer, 'web')
            ->get(route('customer.checkout.confirmation', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Pending Payment')
            ->assertSee('Pay via UPI and send a screenshot on WhatsApp.')
            ->assertSee('coffee@upi')
            ->assertSee('+919999999999')
            ->assertDontSee('Production Cost')
            ->assertDontSee('Margin');

        $this->assertNotSame('', $checkoutToken);
        $this->assertNotNull($checkoutPage);
    }

    public function test_duplicate_checkout_submission_reuses_existing_order_token(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '7.25');

        $this->actingAs($customer, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        $this->actingAs($customer, 'web')->get(route('customer.checkout.show'));
        $checkoutToken = (string) session(config('coffee.checkout.session_token_key'));

        $payload = [
            'checkout_token' => $checkoutToken,
            'fulfilment_method' => 'takeaway',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone ?? '9999999999',
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone ?? '9999999999',
            'customer_notes' => null,
            'pickup_notes' => null,
        ];

        $this->actingAs($customer, 'web')->post(route('customer.checkout.store'), $payload);
        $order = Order::query()->firstOrFail();

        $this->actingAs($customer, 'web')
            ->post(route('customer.checkout.store'), $payload)
            ->assertRedirect(route('customer.checkout.confirmation', $order));

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_failure_preserves_cart_and_rolls_back_created_orders(): void
    {
        $customer = User::factory()->customer()->create([
            'phone' => '9999999999',
        ]);
        $variant = $this->makePurchasableVariant(price: '11.00');

        $this->actingAs($customer, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        $this->actingAs($customer, 'web')->get(route('customer.checkout.show'));
        $checkoutToken = (string) session(config('coffee.checkout.session_token_key'));

        $mock = \Mockery::mock(OrderServiceInterface::class);
        $mock->shouldReceive('expireDuePendingPaymentOrdersForCustomer')->zeroOrMoreTimes()->andReturn(0);
        $mock->shouldReceive('store')
            ->once()
            ->andReturnUsing(function () use ($customer): never {
                Order::factory()->create([
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    'pickup_name' => $customer->name,
                    'pickup_phone' => $customer->phone,
                    'status' => OrderStatus::PendingPayment,
                ]);

                throw new RuntimeException('Checkout failed after order creation.');
            });
        $mock->shouldReceive('transition')->never();
        $mock->shouldReceive('availableTransitions')->never();
        $this->app->instance(OrderServiceInterface::class, $mock);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($customer, 'web')
                ->post(route('customer.checkout.store'), [
                    'checkout_token' => $checkoutToken,
                    'fulfilment_method' => 'takeaway',
                    'customer_name' => $customer->name,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    'pickup_name' => $customer->name,
                    'pickup_phone' => $customer->phone,
                ]);
            $this->fail('The checkout request should have thrown an exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Checkout failed after order creation.', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(1, $customer->cart()->firstOrFail()->items()->count());
    }

    public function test_checkout_confirmation_and_order_detail_are_customer_owned(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9999999999']);
        $otherCustomer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '8.50', productName: 'Matcha Latte');

        $this->actingAs($customer, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        $this->actingAs($customer, 'web')->get(route('customer.checkout.show'));
        $checkoutToken = (string) session(config('coffee.checkout.session_token_key'));

        $this->actingAs($customer, 'web')
            ->post(route('customer.checkout.store'), [
                'checkout_token' => $checkoutToken,
                'fulfilment_method' => 'takeaway',
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'pickup_name' => $customer->name,
                'pickup_phone' => $customer->phone,
            ]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($otherCustomer, 'web')
            ->get(route('customer.checkout.confirmation', $order))
            ->assertForbidden();

        $this->actingAs($otherCustomer, 'web')
            ->get(route('customer.orders.show', $order))
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
