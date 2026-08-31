<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderFulfilmentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_takeaway_checkout_persists_pickup_details_without_delivery_fee(): void
    {
        config()->set('coffee.fulfilment.delivery_disclaimer', 'Config-only delivery disclaimer.');

        WebsiteSetting::query()->where(
            'key',
            WebsiteSettingKey::FulfilmentDeliveryDisclaimer->value,
        )->update([
            'value' => 'Delivery will be arranged through a third-party service. Delivery charges are payable separately by the customer.',
        ]);

        $customer = User::factory()->customer()->create([
            'name' => 'Takeaway Customer',
            'phone' => '9999999999',
        ]);
        $variant = $this->makePurchasableVariant(price: '8.00');

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $summary = $this->getJson(route('api.v1.checkout.summary'))
            ->assertOk()
            ->assertJsonPath('meta.fulfilment.methods.0.value', 'takeaway')
            ->assertJsonPath('meta.fulfilment.methods.1.value', 'delivery')
            ->assertJsonPath(
                'meta.fulfilment.delivery_disclaimer',
                'Delivery will be arranged through a third-party service. Delivery charges are payable separately by the customer.',
            );

        $checkoutToken = (string) $summary->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $checkoutToken,
            'fulfilment_method' => 'takeaway',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => 'Counter Pickup',
            'pickup_phone' => '8888888888',
            'customer_notes' => 'Extra hot',
            'pickup_notes' => 'Arriving soon',
        ])
            ->assertCreated()
            ->assertJsonPath('data.fulfilment_method', 'takeaway')
            ->assertJsonPath('data.fulfilment_method_label', 'Takeaway')
            ->assertJsonPath('data.pickup_name', 'Counter Pickup')
            ->assertJsonPath('data.delivery_fee_amount', null)
            ->assertJsonPath('data.total_amount', '8.00')
            ->assertJsonPath('data.status_label', 'Pending Payment');

        $order = Order::query()->firstOrFail();

        $this->assertSame(OrderFulfilmentMethod::Takeaway, $order->fulfilment_method);
        $this->assertNull($order->delivery_address);
        $this->assertNull($order->delivery_fee_amount);
        $this->assertSame('8.00', $order->total_amount);
    }

    public function test_delivery_checkout_requires_address_and_phone_and_persists_snapshot(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Delivery Customer',
            'phone' => '9777777777',
        ]);
        $variant = $this->makePurchasableVariant(price: '10.50');

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $checkoutToken = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $checkoutToken,
            'fulfilment_method' => 'delivery',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_address' => null,
            'delivery_phone' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_address', 'delivery_phone']);

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $checkoutToken,
            'fulfilment_method' => 'delivery',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_address' => "42 Brew Lane\nBengaluru",
            'delivery_phone' => '9666666666',
            'delivery_contact_name' => 'Door Contact',
            'delivery_notes' => 'Call on arrival',
            'customer_notes' => 'No onion',
        ])
            ->assertCreated()
            ->assertJsonPath('data.fulfilment_method', 'delivery')
            ->assertJsonPath('data.delivery_address', "42 Brew Lane\nBengaluru")
            ->assertJsonPath('data.delivery_phone', '9666666666')
            ->assertJsonPath('data.delivery_contact_name', 'Door Contact')
            ->assertJsonPath('data.delivery_notes', 'Call on arrival')
            ->assertJsonPath('data.delivery_fee_amount', null)
            ->assertJsonPath('data.total_amount', '21.00')
            ->assertJsonPath(
                'data.delivery_disclaimer',
                'Delivery will be arranged through a third-party service. Delivery charges are payable separately by the customer.',
            );

        $order = Order::query()->firstOrFail();

        $this->assertSame(OrderFulfilmentMethod::Delivery, $order->fulfilment_method);
        $this->assertNull($order->pickup_name);
        $this->assertNull($order->delivery_fee_amount);
        $this->assertSame('21.00', $order->total_amount);
        $this->assertSame($order->subtotal, $order->total_amount);
    }

    public function test_delivery_ready_status_uses_ready_for_delivery_label(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->delivery()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::ReadyForPickup,
            'ready_for_pickup_at' => now(),
            'payment_confirmed_at' => now(),
            'payment_status' => 'confirmed',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_pickup')
            ->assertJsonPath('data.status_label', 'Ready for delivery')
            ->assertJsonPath('data.fulfilment_method', 'delivery');
    }

    public function test_checkout_idempotency_is_unchanged_for_takeaway_orders(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9555555555']);
        $variant = $this->makePurchasableVariant(price: '6.00');

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $checkoutToken = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $payload = [
            'checkout_token' => $checkoutToken,
            'fulfilment_method' => 'takeaway',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
        ];

        $first = $this->postJson(route('api.v1.checkout.store'), $payload)->assertCreated();
        $second = $this->postJson(route('api.v1.checkout.store'), $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
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
            'is_active' => true,
            'is_available' => true,
        ]);
    }
}
