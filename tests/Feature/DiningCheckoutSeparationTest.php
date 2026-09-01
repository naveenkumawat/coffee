<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiningCheckoutSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_forged_dine_in_checkout_is_rejected_while_takeaway_and_delivery_work(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            ['value' => '1'],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::OrderingManualClosed->value],
            ['value' => '0'],
        );

        $customer = User::factory()->customer()->create([
            'name' => 'Checkout Customer',
            'email' => 'checkout-dining@coffee.local',
            'phone' => '9000000099',
        ]);
        $variant = $this->makePurchasableVariant();
        $table = CafeTable::factory()->create(['is_active' => true]);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $summary = $this->getJson(route('api.v1.checkout.summary'))->assertOk();
        $token = (string) $summary->json('meta.checkout_token');
        $this->assertSame(
            ['takeaway', 'delivery'],
            collect($summary->json('meta.fulfilment.methods'))->pluck('value')->all(),
        );
        $this->assertTrue((bool) $summary->json('meta.fulfilment.dining_enabled'));

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => OrderFulfilmentMethod::DineIn->value,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'cafe_table_id' => $table->id,
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $takeawaySummary = $this->getJson(route('api.v1.checkout.summary'))->assertOk();

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => (string) $takeawaySummary->json('meta.checkout_token'),
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway->value,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
            'payment_method' => 'manual_upi',
        ])->assertSuccessful();

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $deliverySummary = $this->getJson(route('api.v1.checkout.summary'))->assertOk();

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => (string) $deliverySummary->json('meta.checkout_token'),
            'fulfilment_method' => OrderFulfilmentMethod::Delivery->value,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_address' => '12 Demo Street',
            'delivery_phone' => $customer->phone,
            'payment_method' => 'manual_upi',
        ])->assertSuccessful();
    }

    protected function makePurchasableVariant(): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => '5.00',
            'is_active' => true,
            'is_available' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);
    }
}
