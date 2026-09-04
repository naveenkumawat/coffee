<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnifiedPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('coffee.payments.upi_id', 'demo@upi');
        config()->set('coffee.payments.methods.cash.enabled', true);
        config()->set('coffee.payments.methods.manual_upi.enabled', true);
        config()->set('coffee.payments.methods.razorpay.enabled', false);
        config()->set('coffee.payments.methods.payu.enabled', false);
        config()->set('coffee.payments.methods.paytm.enabled', false);
        config()->set('coffee.payments.methods.phonepe.enabled', false);
    }

    public function test_disabled_methods_are_not_exposed_and_rejected(): void
    {
        config()->set('coffee.payments.methods.cash.enabled', false);
        config()->set('coffee.payments.methods.manual_upi.enabled', false);
        config()->set('coffee.payments.methods.razorpay.enabled', true);
        config()->set('coffee.payments.gateways.razorpay.key_id', 'rzp_test_key');
        config()->set('coffee.payments.gateways.razorpay.key_secret', 'rzp_test_secret');

        $customer = User::factory()->customer()->cashTakeawayAllowed()->create(['phone' => '9111000001']);
        Sanctum::actingAs($customer);

        $takeaway = collect($this->getJson(route('api.v1.payment-methods.index'))->assertOk()->json('data.by_fulfilment.takeaway'));
        $this->assertTrue($takeaway->contains(fn (array $row): bool => $row['key'] === 'razorpay'));
        $this->assertFalse($takeaway->contains(fn (array $row): bool => $row['key'] === 'cash'));
        $this->assertFalse($takeaway->contains(fn (array $row): bool => $row['key'] === 'manual_upi'));

        $this->addItemAndCheckout($customer, 'cash')->assertUnprocessable();
    }

    public function test_enabled_but_incomplete_gateway_is_unavailable(): void
    {
        config()->set('coffee.payments.methods.razorpay.enabled', true);
        config()->set('coffee.payments.gateways.razorpay.key_id', null);
        config()->set('coffee.payments.gateways.razorpay.key_secret', null);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $methods = collect($this->getJson(route('api.v1.payment-methods.index'))->json('data.by_fulfilment.takeaway'));
        $this->assertFalse($methods->contains(fn (array $row): bool => $row['key'] === 'razorpay'));
    }

    public function test_no_methods_available_returns_empty_list(): void
    {
        config()->set('coffee.payments.methods.cash.enabled', false);
        config()->set('coffee.payments.methods.manual_upi.enabled', false);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.payment-methods.index'))
            ->assertOk()
            ->assertJsonPath('data.by_fulfilment.takeaway', []);
    }

    public function test_manual_upi_screenshot_rejected_when_disabled(): void
    {
        Storage::fake('local');
        config()->set('coffee.payments.methods.manual_upi.enabled', false);

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Manual,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($customer);
        $this->post(route('api.v1.orders.payment-proof.upload', $order), [
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_cash_disabled_blocks_customer_selection_even_when_trusted(): void
    {
        config()->set('coffee.payments.methods.cash.enabled', false);
        $customer = User::factory()->customer()->cashTakeawayAllowed()->create(['phone' => '9111000002']);

        $this->addItemAndCheckout($customer, 'cash')->assertUnprocessable();
    }

    public function test_payment_method_api_exposes_no_secrets(): void
    {
        config()->set('coffee.payments.methods.razorpay.enabled', true);
        config()->set('coffee.payments.gateways.razorpay.key_id', 'rzp_test_key');
        config()->set('coffee.payments.gateways.razorpay.key_secret', 'super-secret');
        config()->set('coffee.payments.gateways.razorpay.webhook_secret', 'hook-secret');

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $content = $this->getJson(route('api.v1.payment-methods.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('super-secret', (string) $content);
        $this->assertStringNotContainsString('hook-secret', (string) $content);
        $this->assertStringContainsString('rzp_test_key', (string) $content);
    }

    protected function addItemAndCheckout(User $customer, string $paymentMethod)
    {
        Sanctum::actingAs($customer);
        $variant = $this->makePurchasableVariant('8.00');
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        return $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => $paymentMethod,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone ?: '9000000000',
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone ?: '9000000000',
        ]);
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
