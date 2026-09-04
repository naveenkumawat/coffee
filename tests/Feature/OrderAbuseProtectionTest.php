<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Events\Order\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderAbuseProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_order_below_pending_limit(): void
    {
        $customer = User::factory()->customer()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $response = $this->placeCheckout($customer);

        $response->assertCreated();
        $this->assertSame(2, Order::query()->where('customer_id', $customer->id)->count());
    }

    public function test_third_unresolved_unpaid_order_is_rejected(): void
    {
        $customer = User::factory()->customer()->create();

        Order::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Event::fake([OrderPlaced::class]);

        $this->placeCheckout($customer)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'pending_limit')
            ->assertJsonFragment([
                'message' => 'You already have 2 orders awaiting payment. Complete or cancel one before placing another order.',
            ]);

        Event::assertNotDispatched(OrderPlaced::class);
        $this->assertSame(2, Order::query()->where('customer_id', $customer->id)->count());
    }

    public function test_cancelled_rejected_completed_and_confirmed_do_not_count_toward_pending_limit(): void
    {
        $customer = User::factory()->customer()->create();

        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::Pending,
            'cancelled_at' => now(),
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Rejected,
            'payment_status' => PaymentStatus::Pending,
            'rejected_at' => now(),
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'completed_at' => now(),
        ]);
        Order::factory()->paymentConfirmed()->create([
            'customer_id' => $customer->id,
        ]);

        $this->placeCheckout($customer)->assertCreated();
    }

    public function test_cash_pending_counts_and_cash_received_does_not(): void
    {
        $customer = User::factory()->customer()->create();

        Order::factory()->cash()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
        ]);
        Order::factory()->cash()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
        ]);

        $this->placeCheckout($customer)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'pending_limit');

        Order::query()->where('customer_id', $customer->id)->update([
            'payment_status' => PaymentStatus::Confirmed->value,
            'status' => OrderStatus::PaymentConfirmed->value,
            'payment_confirmed_at' => now(),
        ]);

        $this->placeCheckout($customer)->assertCreated();
    }

    public function test_trusted_cash_does_not_bypass_pending_limit(): void
    {
        $customer = User::factory()->customer()->cashTakeawayAllowed()->create([
            'name' => 'Trusted Limit',
            'phone' => '9111000001',
        ]);

        Order::factory()->count(2)->cash()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($customer);
        $variant = $this->makePurchasableVariant('8.00');
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();
        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'cash',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'pending_limit');
    }

    public function test_blocked_customer_checkout_is_rejected_without_exposing_reason(): void
    {
        $customer = User::factory()->customer()->orderingBlocked('Internal abuse notes')->create();

        $response = $this->placeCheckout($customer)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'ordering_blocked')
            ->assertJsonPath('message', 'Ordering is currently unavailable for this account. Please contact the cafe.');

        $this->assertStringNotContainsString('Internal abuse notes', (string) $response->getContent());
    }

    public function test_administrator_can_block_and_unblock_ordering_without_changing_history_or_cash_trust(): void
    {
        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->cashTakeawayAllowed()->create();
        $existing = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'total_amount' => '55.00',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.users.block-ordering', $customer), [
                'ordering_blocked_reason' => 'Repeated unpaid orders',
            ])
            ->assertRedirect(route('administrator.users.show', $customer));

        $customer->refresh();
        $this->assertTrue($customer->ordering_blocked);
        $this->assertSame('Repeated unpaid orders', $customer->ordering_blocked_reason);
        $this->assertTrue($customer->cash_takeaway_allowed);
        $this->assertSame('55.00', $existing->fresh()->total_amount);
        $this->assertSame(PaymentStatus::Pending, $existing->fresh()->payment_status);

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.users.unblock-ordering', $customer))
            ->assertRedirect(route('administrator.users.show', $customer));

        $customer->refresh();
        $this->assertFalse($customer->ordering_blocked);
        $this->assertNull($customer->ordering_blocked_at);
        $this->assertNull($customer->ordering_blocked_reason);
        $this->assertTrue($customer->cash_takeaway_allowed);
        $this->assertSame(PaymentStatus::Pending, $existing->fresh()->payment_status);

        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->placeCheckout($customer)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'pending_limit');
    }

    public function test_unblocked_customer_can_checkout_when_eligible(): void
    {
        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->orderingBlocked()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.users.unblock-ordering', $customer))
            ->assertRedirect();

        $this->placeCheckout($customer->fresh())->assertCreated();
    }

    public function test_barista_and_customer_cannot_block_or_unblock_ordering(): void
    {
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();
        $target = User::factory()->customer()->create();

        $this->actingAs($barista, 'admin')
            ->post(route('administrator.users.block-ordering', $target), [
                'ordering_blocked_reason' => 'Nope',
            ])
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->post(route('administrator.users.unblock-ordering', $target))
            ->assertForbidden();

        $this->actingAs($customer, 'web')
            ->post(route('administrator.users.block-ordering', $target), [
                'ordering_blocked_reason' => 'Self service',
            ])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->ordering_blocked);
    }

    public function test_checkout_rate_limit_returns_429(): void
    {
        $this->setSecuritySetting(WebsiteSettingKey::OrderSecurityCheckoutAttemptsPer10Minutes, '1');

        $customer = User::factory()->customer()->create();
        $this->placeCheckout($customer)->assertCreated();

        RateLimiter::clear('order_security:order_create:'.$customer->id);

        $this->placeCheckout($customer)
            ->assertStatus(429)
            ->assertJsonPath('code', 'rate_limit');
    }

    public function test_payment_proof_rate_limit_returns_429_without_clearing_existing_proof(): void
    {
        $this->setSecuritySetting(WebsiteSettingKey::OrderSecurityPaymentProofAttemptsPer15Minutes, '1');

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => 'UTRRATELIMIT001',
        ])->assertOk();

        $txn = $order->fresh()->payment_transaction_id;
        $this->assertSame('UTRRATELIMIT001', $txn);

        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => 'UTRRATELIMIT002',
        ])
            ->assertStatus(429)
            ->assertJsonPath('code', 'rate_limit');

        $this->assertSame($txn, $order->fresh()->payment_transaction_id);
    }

    public function test_duplicate_checkout_reuses_existing_order_and_token_idempotency_is_preserved(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Dup Customer',
            'phone' => '9111000002',
        ]);
        $variant = $this->makePurchasableVariant('7.50');

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');
        $payload = [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
        ];

        $first = $this->postJson(route('api.v1.checkout.store'), $payload)->assertCreated();
        $orderId = (int) $first->json('data.id');

        $second = $this->postJson(route('api.v1.checkout.store'), $payload)->assertOk();
        $this->assertSame($orderId, (int) $second->json('data.id'));
        $this->assertSame(1, Order::query()->where('customer_id', $customer->id)->count());

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();
        $newToken = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');
        $payload['checkout_token'] = $newToken;

        $duplicate = $this->postJson(route('api.v1.checkout.store'), $payload);
        $duplicate->assertSuccessful();
        $this->assertSame($orderId, (int) $duplicate->json('data.id'));
        $this->assertSame(1, Order::query()->where('customer_id', $customer->id)->count());
    }

    public function test_serialized_checkouts_cannot_exceed_pending_limit(): void
    {
        $customer = User::factory()->customer()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->placeCheckout($customer)->assertCreated();
        $this->placeCheckout($customer)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'pending_limit');

        $this->assertSame(2, Order::query()->where('customer_id', $customer->id)->count());
    }

    public function test_demo_seed_and_production_defaults_are_safe(): void
    {
        $fresh = User::factory()->customer()->create();
        $this->assertFalse((bool) $fresh->ordering_blocked);

        $this->seed(DatabaseSeeder::class);

        foreach ([
            WebsiteSettingKey::OrderSecurityEnabled->value => '1',
            WebsiteSettingKey::OrderSecurityMaxOpenUnpaidOrders->value => '2',
            WebsiteSettingKey::OrderSecurityMaxOrdersPerHour->value => '5',
            WebsiteSettingKey::OrderSecurityCheckoutAttemptsPer10Minutes->value => '5',
            WebsiteSettingKey::OrderSecurityPaymentProofAttemptsPer15Minutes->value => '5',
            WebsiteSettingKey::OrderSecurityDuplicateOrderWindowMinutes->value => '3',
        ] as $key => $value) {
            $this->assertDatabaseHas('website_settings', [
                'key' => $key,
                'value' => $value,
            ]);
        }

        $this->assertFalse((bool) User::query()->where('email', 'customer@coffee.local')->value('ordering_blocked'));
        $this->assertTrue((bool) User::query()->where('email', 'blocked.ordering@coffee.local')->value('ordering_blocked'));
        $this->assertSame(2, Order::query()->where('order_number', 'like', 'CC-SEC-%')->count());
        $this->assertNull(User::query()->where('email', 'customer@coffee.local')->value('ordering_blocked_reason'));
    }

    protected function placeCheckout(User $customer): TestResponse
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($customer);
        $variant = $this->makePurchasableVariant('9.00');

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        return $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone ?: '9000000000',
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone ?: '9000000000',
        ]);
    }

    protected function setSecuritySetting(WebsiteSettingKey $key, string $value): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'section' => $key->section(),
                'value_type' => $key->valueType(),
                'value' => $value,
            ],
        );
    }

    protected function makePurchasableVariant(string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create([
            'name' => fake()->unique()->words(2, true),
        ]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => fake()->unique()->words(2, true),
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
