<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Invoice\OrderInvoiceServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_summary_exposes_cash_by_fulfilment_and_trust(): void
    {
        $this->enableDineIn();

        $normal = User::factory()->customer()->create(['cash_takeaway_allowed' => false]);
        $trusted = User::factory()->customer()->cashTakeawayAllowed()->create();
        $variant = $this->makePurchasableVariant('10.00');

        Sanctum::actingAs($normal);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $summary = $this->getJson(route('api.v1.checkout.summary'))->assertOk();
        $methods = $summary->json('meta.payment_methods');

        $this->assertContains('cash', array_column($methods['dine_in'], 'key'));
        $this->assertNotContains('cash', array_column($methods['takeaway'], 'key'));
        $this->assertNotContains('cash', array_column($methods['delivery'], 'key'));
        $this->assertContains('manual_upi', array_column($methods['delivery'], 'key'));

        Sanctum::actingAs($trusted);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $trustedSummary = $this->getJson(route('api.v1.checkout.summary'))->assertOk();
        $trustedMethods = $trustedSummary->json('meta.payment_methods');

        $this->assertContains('cash', array_column($trustedMethods['takeaway'], 'key'));
        $this->assertNotContains('cash', array_column($trustedMethods['delivery'], 'key'));
    }

    public function test_normal_customer_takeaway_cash_is_rejected_and_trusted_is_allowed(): void
    {
        $variant = $this->makePurchasableVariant('8.00');
        $normal = User::factory()->customer()->create([
            'name' => 'Normal Customer',
            'phone' => '9111111111',
            'cash_takeaway_allowed' => false,
        ]);
        $trusted = User::factory()->customer()->cashTakeawayAllowed()->create([
            'name' => 'Trusted Customer',
            'phone' => '9222222222',
        ]);

        Sanctum::actingAs($normal);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();
        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'cash',
            'customer_name' => $normal->name,
            'customer_email' => $normal->email,
            'customer_phone' => $normal->phone,
            'pickup_name' => $normal->name,
            'pickup_phone' => $normal->phone,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);

        Sanctum::actingAs($trusted);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();
        $trustedToken = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $trustedToken,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'cash',
            'customer_name' => $trusted->name,
            'customer_email' => $trusted->email,
            'customer_phone' => $trusted->phone,
            'pickup_name' => $trusted->name,
            'pickup_phone' => $trusted->phone,
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('meta.payment', null);

        $order = Order::query()->where('customer_id', $trusted->id)->firstOrFail();
        $this->assertSame(PaymentMethod::Cash, $order->payment_method);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertFalse($order->canUploadPaymentProof());
    }

    public function test_dining_session_cash_allowed_and_delivery_cash_rejected(): void
    {
        $this->enableDineIn();
        $table = CafeTable::factory()->create(['is_active' => true, 'code' => 'T1']);
        $customer = User::factory()->customer()->create(['phone' => '9333333333']);
        $variant = $this->makePurchasableVariant('12.00');

        Sanctum::actingAs($customer);

        $start = $this->postJson(route('api.v1.dining.sessions.store'), [
            'cafe_table_id' => $table->id,
            'guest_count' => 2,
        ])->assertCreated();

        $sessionId = (int) $start->json('data.id');

        $this->postJson(route('api.v1.dining.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('api.v1.dining.sessions.rounds.store', $sessionId))
            ->assertCreated();

        $this->postJson(route('api.v1.dining.sessions.request-bill', $sessionId))
            ->assertOk();

        $this->postJson(route('api.v1.dining.sessions.payment-method', $sessionId), [
            'payment_method' => 'cash',
        ])->assertOk()
            ->assertJsonPath('data.payment_method', 'cash');

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();
        $deliveryToken = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $deliveryToken,
            'fulfilment_method' => 'delivery',
            'payment_method' => 'cash',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_address' => '42 Brew Lane',
            'delivery_phone' => $customer->phone,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_admin_can_toggle_cash_takeaway_trust_but_barista_cannot(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create(['cash_takeaway_allowed' => false]);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.users.update', $customer), [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'role' => UserRole::Customer->value,
                'is_active' => '1',
                'cash_takeaway_allowed' => '1',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('administrator.users.edit', $customer));

        $this->assertTrue($customer->fresh()->cash_takeaway_allowed);

        $this->actingAs($barista, 'admin')
            ->put(route('administrator.users.update', $customer), [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'role' => UserRole::Customer->value,
                'is_active' => '1',
                'cash_takeaway_allowed' => '0',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->cash_takeaway_allowed);
    }

    public function test_cash_orders_block_proof_upload_while_upi_still_allows_it(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create();
        $cashOrder = Order::factory()->cash()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);
        $upiOrder = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.orders.payment-proof.upload', $cashOrder), [
            'transaction_id' => 'UTRCASHBLOCK01',
        ])->assertForbidden();

        $this->postJson(route('api.v1.orders.payment-proof.upload', $upiOrder), [
            'transaction_id' => 'UTRCASHALLOW01',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'awaiting_review')
            ->assertJsonPath('data.payment_transaction_id', 'UTRCASHALLOW01');
    }

    public function test_mark_cash_received_by_admin_and_operator_with_duplicate_guard(): void
    {
        $admin = User::factory()->manager()->create();
        $operator = User::factory()->operator()->create();
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->cash()->dineIn()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Accepted,
            'payment_status' => PaymentStatus::Pending,
            'payment_confirmed_at' => null,
        ]);

        $this->actingAs($operator, 'admin')
            ->post(route('operator.orders.cash.receive', $order))
            ->assertRedirect(route('operator.orders.show', $order));

        $order->refresh();
        $this->assertSame(PaymentStatus::Confirmed, $order->payment_status);
        $this->assertSame($operator->id, $order->payment_received_by_id);
        $this->assertNotNull($order->payment_confirmed_at);
        $this->assertSame(OrderStatus::Accepted, $order->status);

        $this->actingAs($admin, 'admin')
            ->from(route('administrator.orders.show', $order))
            ->post(route('administrator.orders.cash.receive', $order))
            ->assertRedirect(route('administrator.orders.show', $order))
            ->assertSessionHasErrors('payment');

        $upi = Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Manual,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.orders.cash.receive', $upi))
            ->assertForbidden();
    }

    public function test_revoking_trust_does_not_rewrite_existing_cash_order_snapshot(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->customer()->cashTakeawayAllowed()->create();
        $order = Order::factory()->cash()->takeaway()->create([
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash,
        ]);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.users.update', $customer), [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'role' => UserRole::Customer->value,
                'is_active' => '1',
                'cash_takeaway_allowed' => '0',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect();

        $this->assertFalse($customer->fresh()->cash_takeaway_allowed);
        $this->assertSame(PaymentMethod::Cash, $order->fresh()->payment_method);
    }

    public function test_invoice_and_thermal_show_cash_without_upi_instructions(): void
    {
        $admin = User::factory()->manager()->create();
        $order = Order::factory()->cash()->paymentConfirmed()->create([
            'payment_method' => PaymentMethod::Cash,
            'total_amount' => '42.00',
        ]);

        $invoice = $this->app->make(OrderInvoiceServiceInterface::class)->build($order);
        $this->assertSame('Cash', $invoice->paymentMethodLabel);

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.orders.invoice.print', $order))
            ->assertOk()
            ->assertSee('Payment Method')
            ->assertSee('Cash')
            ->assertDontSee('UPI ID', false);

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.orders.invoice.receipt', ['order' => $order, 'width' => 80]))
            ->assertOk()
            ->assertSee('CASH · PAID')
            ->assertDontSee('Upload payment', false);
    }

    public function test_production_defaults_and_demo_cash_seed_are_safe(): void
    {
        $customer = User::factory()->customer()->create();
        $this->assertFalse((bool) $customer->cash_takeaway_allowed);

        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(
            (bool) User::query()->where('email', 'customer@coffee.local')->value('cash_takeaway_allowed'),
        );
        $this->assertTrue(
            (bool) User::query()->where('email', 'priya@coffee.local')->value('cash_takeaway_allowed'),
        );
        $this->assertTrue(
            (bool) User::query()->where('email', 'arjun@coffee.local')->value('cash_takeaway_allowed'),
        );

        $this->assertDatabaseHas('orders', [
            'order_number' => 'CC-CASH-0001',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'fulfilment_method' => 'dine_in',
        ]);
        $this->assertDatabaseHas('orders', [
            'order_number' => 'CC-CASH-0002',
            'payment_method' => 'cash',
            'payment_status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('orders', [
            'order_number' => 'CC-CASH-0003',
            'payment_method' => 'cash',
            'fulfilment_method' => 'takeaway',
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'order_number' => 'CC-CASH-0004',
            'payment_method' => 'cash',
            'fulfilment_method' => 'takeaway',
            'payment_status' => 'confirmed',
        ]);
    }

    public function test_cash_order_api_resource_hides_proof_controls(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->cash()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.payment_proof.can_upload', false)
            ->assertJsonPath('data.payment_proof.uploaded', false);
    }

    protected function enableDineIn(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            ['value' => '1'],
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

        return ProductVariant::factory()->withConsumableRecipe()->create([
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
