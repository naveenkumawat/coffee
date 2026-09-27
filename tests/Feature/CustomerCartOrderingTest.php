<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\PublicCache\PublicCacheVersionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCartOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_takeaway_and_delivery_checkout_succeed_when_customer_cart_is_enabled(): void
    {
        $this->getJson(route('api.v1.app-bootstrap.show'))
            ->assertOk()
            ->assertJsonPath('data.cart_enabled', true);
        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.fulfilment.cart_enabled', true);

        $customer = User::factory()->customer()->create(['phone' => '9555555551']);
        $variant = $this->makePurchasableVariant('8.00');

        Sanctum::actingAs($customer);

        $takeaway = $this->checkout($customer, $variant, 'takeaway');
        $takeaway->assertCreated()->assertJsonPath('data.fulfilment_method', 'takeaway');

        $delivery = $this->checkout($customer, $variant, 'delivery');
        $delivery->assertCreated()->assertJsonPath('data.fulfilment_method', 'delivery');
    }

    public function test_new_takeaway_and_delivery_checkout_are_rejected_when_customer_cart_is_disabled(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9555555552']);
        $variant = $this->makePurchasableVariant('8.00');

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->disableCustomerCart();

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['cart']);

        $this->assertSame(1, CartItem::query()->count());

        $token = $this->checkoutTokenFromSummary();

        $this->postJson(route('api.v1.checkout.store'), $this->checkoutPayload($customer, 'takeaway', $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['checkout']);

        $this->postJson(route('api.v1.checkout.store'), $this->checkoutPayload($customer, 'delivery', $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['checkout']);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_dining_round_still_succeeds_when_customer_cart_is_disabled(): void
    {
        $this->disableCustomerCart();
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            [
                'section' => WebsiteSettingKey::FulfilmentDineInEnabled->section(),
                'value_type' => 'boolean',
                'value' => '1',
            ],
        );

        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['code' => 'C1', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('6.00');

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

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_existing_order_stays_readable_and_replayable_when_cart_is_disabled(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9555555553']);
        $variant = $this->makePurchasableVariant('8.00');

        Sanctum::actingAs($customer);
        $created = $this->checkout($customer, $variant, 'takeaway')->assertCreated();
        $orderId = (int) $created->json('data.id');
        $order = Order::query()->findOrFail($orderId);
        $this->disableCustomerCart();

        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value);

        $this->postJson(route('api.v1.checkout.store'), $this->checkoutPayload($customer, 'takeaway', $order->checkout_token))
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);

        $this->assertSame(1, Order::query()->count());
        $this->assertNotSame('', (string) $order->checkout_token);

        $this->postJson(route('api.v1.orders.cancel', $order))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_disabling_customer_cart_bumps_public_cache_without_deleting_cart_items(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9555555554']);
        $manager = User::factory()->manager()->create();
        $variant = $this->makePurchasableVariant('8.00');

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->assertSame(1, CartItem::query()->count());

        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'ordering',
                WebsiteSettingKey::CustomerCartEnabled->value => '0',
                WebsiteSettingKey::OrderSecurityEnabled->value => '1',
                WebsiteSettingKey::OrderingManualClosed->value => '0',
            ])
            ->assertRedirect();

        $after = app(PublicCacheVersionServiceInterface::class)->currentVersion();
        $this->assertNotSame($before, $after);

        $this->getJson(route('api.v1.app-bootstrap.show'))
            ->assertOk()
            ->assertJsonPath('data.cache_version', $after)
            ->assertJsonPath('data.cart_enabled', false)
            ->assertJsonPath('data.dining_enabled', false);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.fulfilment.cart_enabled', false);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.cart.show'))
            ->assertOk();
        $this->assertSame(1, CartItem::query()->count());

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['cart']);

        $this->assertSame(1, CartItem::query()->count());
    }

    protected function disableCustomerCart(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::CustomerCartEnabled->value],
            [
                'section' => WebsiteSettingKey::CustomerCartEnabled->section(),
                'value_type' => 'boolean',
                'value' => '0',
            ],
        );
    }

    protected function checkout(User $customer, ProductVariant $variant, string $method, bool $addItem = true): TestResponse
    {
        if ($addItem) {
            $this->postJson(route('api.v1.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ])->assertCreated();
        }

        $token = $addItem ? $this->checkoutTokenFromSummary() : 'missing-token';

        return $this->postJson(route('api.v1.checkout.store'), $this->checkoutPayload($customer, $method, $token));
    }

    protected function checkoutTokenFromSummary(): string
    {
        return (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkoutPayload(User $customer, string $method, ?string $token): array
    {
        $payload = [
            'checkout_token' => $token,
            'fulfilment_method' => $method,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
        ];

        if ($method === 'delivery') {
            $payload['delivery_address'] = "42 Brew Lane\nBengaluru";
            $payload['delivery_phone'] = '9666666666';
            $payload['delivery_contact_name'] = 'Door Contact';
        } else {
            $payload['pickup_name'] = $customer->name;
            $payload['pickup_phone'] = $customer->phone;
        }

        return $payload;
    }

    protected function makePurchasableVariant(string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'House Latte',
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);
    }
}
