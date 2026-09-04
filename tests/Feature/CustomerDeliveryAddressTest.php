<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\ProductServingUnit;
use App\Models\CustomerDeliveryAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDeliveryAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_crud_delivery_addresses_with_single_default(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9444555666']);
        Sanctum::actingAs($customer);

        $first = $this->postJson(route('api.v1.account.delivery-addresses.store'), [
            'label' => 'Home',
            'recipient_name' => 'Home Recipient',
            'phone' => '9444555666',
            'address_line_1' => '12 Bean Street',
            'city' => 'Bengaluru',
            'state' => 'KA',
            'postal_code' => '560001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->json('data');

        $second = $this->postJson(route('api.v1.account.delivery-addresses.store'), [
            'label' => 'Office',
            'recipient_name' => 'Office Recipient',
            'phone' => '9555666777',
            'address_line_1' => '88 Roast Road',
            'city' => 'Bengaluru',
            'state' => 'KA',
            'postal_code' => '560002',
            'is_default' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->json('data');

        $this->assertFalse(CustomerDeliveryAddress::query()->findOrFail($first['id'])->is_default);
        $this->assertTrue(CustomerDeliveryAddress::query()->findOrFail($second['id'])->is_default);
        $this->assertSame(1, CustomerDeliveryAddress::query()->where('customer_id', $customer->id)->where('is_default', true)->count());

        $this->putJson(route('api.v1.account.delivery-addresses.update', $first['id']), [
            'label' => 'Home Updated',
            'recipient_name' => 'Home Recipient',
            'phone' => '9444555666',
            'address_line_1' => '14 Bean Street',
            'city' => 'Bengaluru',
            'state' => 'KA',
            'postal_code' => '560001',
        ])
            ->assertOk()
            ->assertJsonPath('data.address_line_1', '14 Bean Street');

        $this->postJson(route('api.v1.account.delivery-addresses.default', $first['id']))
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertTrue(CustomerDeliveryAddress::query()->findOrFail($first['id'])->fresh()->is_default);
        $this->assertFalse(CustomerDeliveryAddress::query()->findOrFail($second['id'])->fresh()->is_default);

        $this->deleteJson(route('api.v1.account.delivery-addresses.destroy', $first['id']))
            ->assertOk();

        $this->assertSoftDeleted('customer_delivery_addresses', ['id' => $first['id']]);
        $this->assertFalse(CustomerDeliveryAddress::query()->findOrFail($second['id'])->is_default);
    }

    public function test_customer_isolation_for_delivery_addresses(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $address = CustomerDeliveryAddress::factory()->create([
            'customer_id' => $owner->id,
            'is_default' => true,
        ]);

        Sanctum::actingAs($other);

        $this->getJson(route('api.v1.account.delivery-addresses.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->putJson(route('api.v1.account.delivery-addresses.update', $address), [
            'recipient_name' => 'Hacker',
            'phone' => '9000000000',
            'address_line_1' => 'Nope',
            'city' => 'X',
            'state' => 'Y',
            'postal_code' => '000000',
        ])->assertNotFound();

        $this->deleteJson(route('api.v1.account.delivery-addresses.destroy', $address))->assertNotFound();
        $this->postJson(route('api.v1.account.delivery-addresses.default', $address))->assertNotFound();
    }

    public function test_checkout_uses_saved_address_snapshot_and_ignores_later_edits(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Delivery Customer',
            'phone' => '9666777888',
        ]);
        $address = CustomerDeliveryAddress::factory()->default()->create([
            'customer_id' => $customer->id,
            'label' => 'Home',
            'recipient_name' => 'Door Contact',
            'phone' => '9777888999',
            'address_line_1' => '42 Brew Lane',
            'city' => 'Bengaluru',
            'state' => 'KA',
            'postal_code' => '560001',
        ]);
        $variant = $this->makePurchasableVariant('10.00');

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $summary = $this->getJson(route('api.v1.checkout.summary'))
            ->assertOk()
            ->assertJsonPath('meta.delivery_addresses.0.id', $address->id)
            ->assertJsonPath('meta.delivery_addresses.0.is_default', true);

        $token = (string) $summary->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'delivery',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_address_id' => $address->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.fulfilment_method', 'delivery')
            ->assertJsonPath('data.delivery_phone', '9777888999')
            ->assertJsonPath('data.delivery_contact_name', 'Door Contact');

        $order = Order::query()->firstOrFail();
        $snapshot = $order->delivery_address;
        $this->assertSame(OrderFulfilmentMethod::Delivery, $order->fulfilment_method);
        $this->assertStringContainsString('42 Brew Lane', (string) $snapshot);

        $address->update(['address_line_1' => '999 Changed Lane']);
        $this->assertSame($snapshot, $order->fresh()->delivery_address);

        $address->delete();
        $this->assertSame($snapshot, $order->fresh()->delivery_address);
    }

    public function test_checkout_inline_address_can_save_and_make_default(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Inline Customer',
            'phone' => '9888999000',
        ]);
        CustomerDeliveryAddress::factory()->default()->create([
            'customer_id' => $customer->id,
            'label' => 'Old Default',
        ]);
        $variant = $this->makePurchasableVariant('11.00');

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'delivery',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_contact_name' => 'New Contact',
            'delivery_phone' => '9888999000',
            'address_line_1' => '1 New Street',
            'city' => 'Bengaluru',
            'state' => 'KA',
            'postal_code' => '560010',
            'save_delivery_address' => true,
            'make_default_address' => true,
            'address_label' => 'New Home',
        ])->assertCreated();

        $this->assertSame(2, CustomerDeliveryAddress::query()->where('customer_id', $customer->id)->count());
        $this->assertSame(
            1,
            CustomerDeliveryAddress::query()->where('customer_id', $customer->id)->where('is_default', true)->count(),
        );
        $this->assertTrue(
            CustomerDeliveryAddress::query()
                ->where('customer_id', $customer->id)
                ->where('label', 'New Home')
                ->where('is_default', true)
                ->exists(),
        );
    }

    public function test_checkout_inline_without_save_and_forged_address_rejected(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9000111222']);
        $other = User::factory()->customer()->create();
        $foreign = CustomerDeliveryAddress::factory()->create(['customer_id' => $other->id]);
        $variant = $this->makePurchasableVariant('6.50');

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'delivery',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_address_id' => $foreign->id,
        ])->assertUnprocessable();

        $this->assertSame(0, CustomerDeliveryAddress::query()->where('customer_id', $customer->id)->count());

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'delivery',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_address' => "7 Freeform Lane\nBengaluru",
            'delivery_phone' => '9000111222',
            'save_delivery_address' => false,
        ])->assertCreated();

        $this->assertSame(0, CustomerDeliveryAddress::query()->where('customer_id', $customer->id)->count());
        $this->assertSame("7 Freeform Lane\nBengaluru", Order::query()->firstOrFail()->delivery_address);
    }

    public function test_takeaway_checkout_does_not_require_delivery_address(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9111222334']);
        $variant = $this->makePurchasableVariant('5.00');

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
        ])
            ->assertCreated()
            ->assertJsonPath('data.delivery_address', null);
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
