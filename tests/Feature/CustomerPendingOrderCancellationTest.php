<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerPendingOrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_unpaid_pending_payment_order(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_confirmed_at' => null,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true);

        $this->postJson(route('api.v1.orders.cancel', $order))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.status_label', 'Cancelled')
            ->assertJsonPath('data.can_cancel', false);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('customer', $order->cancellation_source);
        $this->assertSame('customer_cancelled_before_payment', $order->cancellation_reason);
        $this->assertNotNull($order->cancelled_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::Cancelled->value,
            'notes' => 'customer_cancelled_before_payment',
        ]);
    }

    public function test_duplicate_cancel_is_idempotent(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.orders.cancel', $order))->assertOk();
        $this->postJson(route('api.v1.orders.cancel', $order))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(1, $order->statusHistory()->where('to_status', OrderStatus::Cancelled->value)->count());
    }

    public function test_paid_and_downstream_statuses_cannot_be_customer_cancelled(): void
    {
        $customer = User::factory()->customer()->create();

        $cases = [
            Order::factory()->paymentConfirmed()->create(['customer_id' => $customer->id]),
            Order::factory()->create([
                'customer_id' => $customer->id,
                'status' => OrderStatus::Accepted,
                'payment_status' => PaymentStatus::Confirmed,
                'payment_confirmed_at' => now(),
                'accepted_at' => now(),
            ]),
            Order::factory()->create([
                'customer_id' => $customer->id,
                'status' => OrderStatus::Preparing,
                'payment_status' => PaymentStatus::Confirmed,
                'payment_confirmed_at' => now(),
                'preparing_at' => now(),
            ]),
            Order::factory()->create([
                'customer_id' => $customer->id,
                'status' => OrderStatus::ReadyForPickup,
                'payment_status' => PaymentStatus::Confirmed,
                'payment_confirmed_at' => now(),
                'ready_for_pickup_at' => now(),
            ]),
            Order::factory()->create([
                'customer_id' => $customer->id,
                'status' => OrderStatus::Completed,
                'payment_status' => PaymentStatus::Confirmed,
                'payment_confirmed_at' => now(),
                'completed_at' => now(),
            ]),
            Order::factory()->create([
                'customer_id' => $customer->id,
                'status' => OrderStatus::Rejected,
                'payment_status' => PaymentStatus::Pending,
                'rejected_at' => now(),
            ]),
        ];

        Sanctum::actingAs($customer);

        foreach ($cases as $order) {
            $this->getJson(route('api.v1.orders.show', $order))
                ->assertOk()
                ->assertJsonPath('data.can_cancel', false);

            $this->postJson(route('api.v1.orders.cancel', $order))->assertForbidden();
        }
    }

    public function test_another_customer_cannot_cancel_order(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $owner->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($other);

        $this->postJson(route('api.v1.orders.cancel', $order))->assertForbidden();
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_cancelled_order_no_longer_counts_toward_pending_limit(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9111222333']);

        $first = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);
        Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.orders.cancel', $first))->assertOk();

        $variant = $this->makePurchasableVariant('9.00');
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
    }

    public function test_dining_round_cannot_use_customer_retail_cancel(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->dineIn()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.orders.cancel', $order))->assertForbidden();
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
