<?php

namespace Tests\Feature;

use App\Enums\CustomerNotificationType;
use App\Enums\OrderStatus;
use App\Enums\ProductServingUnit;
use App\Enums\StaffNotificationType;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Notifications\OrderCustomerNotification;
use App\Notifications\StaffOperationalNotification;
use App\Services\Notification\StaffNotificationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DineInFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_dine_in_disabled_by_default_and_hidden_from_checkout_summary(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '5.00');
        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $summary = $this->getJson(route('api.v1.checkout.summary'))->assertOk();

        $this->assertFalse((bool) $summary->json('meta.fulfilment.dine_in_enabled'));
        $this->assertSame(
            ['takeaway', 'delivery'],
            collect($summary->json('meta.fulfilment.methods'))->pluck('value')->all(),
        );
    }

    public function test_public_cafe_tables_empty_when_dine_in_disabled(): void
    {
        CafeTable::factory()->create(['code' => 'T1', 'is_active' => true]);

        $this->getJson(route('api.v1.cafe-tables.index'))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_enabled_dine_in_exposes_method_and_active_tables_only(): void
    {
        $this->enableDineIn();

        $active = CafeTable::factory()->create([
            'code' => 'T4',
            'name' => 'Window',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        CafeTable::factory()->inactive()->create([
            'code' => 'T9',
            'sort_order' => 2,
        ]);

        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '5.00');
        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $summary = $this->getJson(route('api.v1.checkout.summary'))->assertOk();
        $this->assertTrue((bool) $summary->json('meta.fulfilment.dine_in_enabled'));
        $this->assertTrue((bool) $summary->json('meta.fulfilment.dining_enabled'));
        $this->assertSame(
            ['takeaway', 'delivery'],
            collect($summary->json('meta.fulfilment.methods'))->pluck('value')->all(),
        );

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.fulfilment.dine_in_enabled', true)
            ->assertJsonPath('data.fulfilment.dining_enabled', true);

        $this->getJson(route('api.v1.cafe-tables.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.code', 'T4')
            ->assertJsonMissingPath('data.0.is_active')
            ->assertJsonMissingPath('data.0.sort_order');
    }

    public function test_dine_in_checkout_is_rejected_in_favour_of_dining_sessions(): void
    {
        $this->enableDineIn();
        $table = CafeTable::factory()->create([
            'code' => 'T4',
            'name' => null,
            'is_active' => true,
        ]);

        $customer = User::factory()->customer()->create([
            'name' => 'Dine Customer',
            'phone' => '9111111111',
        ]);
        $variant = $this->makePurchasableVariant(price: '12.00');

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $checkoutToken = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $checkoutToken,
            'fulfilment_method' => 'dine_in',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'cafe_table_id' => $table->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fulfilment_method']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_dine_in_rejected_when_feature_disabled(): void
    {
        $table = CafeTable::factory()->create(['is_active' => true]);
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '7.00');

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $checkoutToken = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $checkoutToken,
            'fulfilment_method' => 'dine_in',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone ?? '9000000000',
            'cafe_table_id' => $table->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fulfilment_method']);
    }

    public function test_table_rename_does_not_change_historical_snapshot(): void
    {
        $this->enableDineIn();
        $table = CafeTable::factory()->create(['code' => 'T4', 'name' => null]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->dineIn($table)->create([
            'customer_id' => $customer->id,
            'table_name_snapshot' => 'T4',
        ]);

        $table->update(['code' => 'T99', 'name' => 'Patio']);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.table_name', 'T4')
            ->assertJsonPath('data.cafe_table_id', $table->id);
    }

    public function test_ready_status_uses_ready_to_serve_for_dine_in(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->dineIn()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::ReadyForPickup,
            'ready_for_pickup_at' => now(),
            'table_name_snapshot' => 'T4',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.status_label', 'Ready to Serve');
    }

    public function test_staff_and_customer_notifications_include_table(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Owner]);
        $order = Order::factory()->dineIn()->create([
            'order_number' => 'CC-310826-0042',
            'table_name_snapshot' => 'T4',
        ]);

        $staff = new StaffOperationalNotification(
            StaffNotificationType::OrderPlaced,
            StaffNotificationContext::forOrder($order),
        );

        $this->assertSame(
            'New dine-in order #CC-310826-0042 — Table T4',
            $staff->toDatabase($admin)['title'],
        );

        $placedHtml = (new OrderCustomerNotification($order, CustomerNotificationType::OrderPlaced))
            ->toMail($order->customer)
            ->render();

        $this->assertStringContainsString('Table: T4', $placedHtml);

        $readyMail = (new OrderCustomerNotification($order, CustomerNotificationType::OrderReady))
            ->toMail($order->customer);

        $this->assertStringContainsString('ready to serve', strtolower($readyMail->subject));
        $this->assertStringContainsString('Table: T4', $readyMail->render());
    }

    public function test_administrator_can_manage_cafe_tables(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.cafe-tables.store'), [
                'code' => 'outdoor 1',
                'name' => 'Patio',
                'sort_order' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $table = CafeTable::query()->where('code', 'OUTDOOR 1')->firstOrFail();
        $this->assertTrue($table->is_active);
        $this->assertSame('Patio', $table->name);

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.cafe-tables.index'))
            ->assertOk()
            ->assertSee('OUTDOOR 1');
    }

    protected function enableDineIn(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            ['value' => '1'],
        );
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
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => $variantName,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_available' => true,
        ]);
    }
}
