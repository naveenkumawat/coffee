<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Order\OrderServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PendingOrderExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_snapshots_payment_expires_at_from_config(): void
    {
        config()->set('coffee.orders.pending_payment_expiry_minutes', 90);

        $customer = User::factory()->customer()->create(['phone' => '9222333444']);
        $variant = $this->makePurchasableVariant('8.00');

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
        ])->assertCreated();

        $order = Order::query()->firstOrFail();
        $this->assertNotNull($order->payment_expires_at);
        $this->assertTrue(
            $order->payment_expires_at->between(
                now()->addMinutes(89),
                now()->addMinutes(91),
            ),
        );
    }

    public function test_unpaid_pending_order_expires_after_window(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'placed_at' => now()->subHours(3),
            'payment_expires_at' => now()->subMinute(),
        ]);

        Artisan::call('coffee:expire-pending-orders');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('system', $order->cancellation_source);
        $this->assertSame('payment_timeout', $order->cancellation_reason);

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.status_label', 'Cancelled — payment window expired')
            ->assertJsonPath('data.can_cancel', false);
    }

    public function test_order_not_expired_before_due_time(): void
    {
        $order = Order::factory()->takeaway()->create([
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_expires_at' => now()->addHour(),
        ]);

        Artisan::call('coffee:expire-pending-orders');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_paid_accepted_and_dining_orders_are_not_expired(): void
    {
        $paid = Order::factory()->paymentConfirmed()->create([
            'payment_expires_at' => now()->subHour(),
        ]);
        $accepted = Order::factory()->create([
            'status' => OrderStatus::Accepted,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_confirmed_at' => now(),
            'payment_expires_at' => now()->subHour(),
            'accepted_at' => now(),
        ]);
        $dining = Order::factory()->dineIn()->create([
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
            'payment_expires_at' => now()->subHour(),
        ]);

        Artisan::call('coffee:expire-pending-orders');

        $this->assertSame(OrderStatus::PaymentConfirmed, $paid->fresh()->status);
        $this->assertSame(OrderStatus::Accepted, $accepted->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $dining->fresh()->status);
    }

    public function test_expiry_is_idempotent_and_race_safe_for_paid_orders(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_expires_at' => now()->subMinute(),
        ]);

        /** @var OrderServiceInterface $orders */
        $orders = app(OrderServiceInterface::class);

        $orders->expirePendingPaymentOrder($order);
        $orders->expirePendingPaymentOrder($order->fresh());

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(1, $order->statusHistory()->where('to_status', OrderStatus::Cancelled->value)->count());

        $paid = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_expires_at' => now()->subMinute(),
        ]);
        $paid->forceFill([
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_confirmed_at' => now(),
        ])->save();

        $result = $orders->expirePendingPaymentOrder($paid->fresh());
        $this->assertSame(OrderStatus::PaymentConfirmed, $result->status);
    }

    public function test_stale_pending_orders_do_not_block_checkout_limit(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9333444555']);

        Order::factory()->count(2)->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'placed_at' => now()->subHours(5),
            'payment_expires_at' => now()->subMinutes(10),
        ]);

        $variant = $this->makePurchasableVariant('7.00');
        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
        ])->assertCreated();

        $this->assertSame(
            2,
            Order::query()
                ->where('customer_id', $customer->id)
                ->where('status', OrderStatus::Cancelled)
                ->where('cancellation_reason', 'payment_timeout')
                ->count(),
        );
    }

    protected function makePurchasableVariant(string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);
    }
}
