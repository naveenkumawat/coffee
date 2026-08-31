<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WebsiteSettingKey;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Invoice\OrderInvoiceServiceInterface;
use App\Services\Tax\TaxCalculatorInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_disabled_leaves_existing_totals_unchanged(): void
    {
        $this->setTaxSettings(enabled: false, percent: '5.00');

        $tax = $this->app->make(TaxCalculatorInterface::class)->calculateForTaxableAmount('100.00');

        $this->assertFalse($tax->enabled);
        $this->assertSame('0.00', $tax->taxAmount);
        $this->assertSame('100.00', $tax->cafeTotal);
    }

    public function test_exclusive_five_percent_calculation_and_rounding(): void
    {
        $this->setTaxSettings(enabled: true, percent: '5.00', inclusive: false);

        $calculator = $this->app->make(TaxCalculatorInterface::class);

        $seventeen = $calculator->calculateForTaxableAmount('17.00');
        $this->assertTrue($seventeen->enabled);
        $this->assertSame('0.85', $seventeen->taxAmount);
        $this->assertSame('17.85', $seventeen->cafeTotal);
        $this->assertSame('17.85', bcadd($seventeen->taxableAmount, $seventeen->taxAmount, 2));

        $multi = $calculator->calculateForTaxableAmount('33.33');
        $this->assertSame('1.67', $multi->taxAmount);
        $this->assertSame('35.00', $multi->cafeTotal);
    }

    public function test_inclusive_five_percent_calculation(): void
    {
        $this->setTaxSettings(enabled: true, percent: '5.00', inclusive: true);

        $tax = $this->app->make(TaxCalculatorInterface::class)->calculateForTaxableAmount('105.00');

        $this->assertTrue($tax->enabled);
        $this->assertTrue($tax->inclusive);
        $this->assertSame('5.00', $tax->taxAmount);
        $this->assertSame('105.00', $tax->cafeTotal);
    }

    public function test_checkout_stores_tax_snapshot_and_payment_total_includes_exclusive_gst(): void
    {
        $this->setTaxSettings(enabled: true, percent: '5.00', inclusive: false);

        $customer = User::factory()->customer()->create([
            'phone' => '9999999999',
        ]);
        $variant = $this->makePurchasableVariant('20.00');

        $this->actingAs($customer, 'web')
            ->post(route('customer.cart.items.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($customer, 'web')->get(route('customer.checkout.show'))->assertOk();
        $checkoutToken = (string) session(config('coffee.checkout.session_token_key'));

        $this->actingAs($customer, 'web')
            ->post(route('customer.checkout.store'), [
                'checkout_token' => $checkoutToken,
                'fulfilment_method' => 'takeaway',
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'pickup_name' => $customer->name,
                'pickup_phone' => $customer->phone,
            ])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertTrue($order->tax_enabled_snapshot);
        $this->assertSame('GST', $order->tax_label_snapshot);
        $this->assertSame('5.00', $order->tax_percent_snapshot);
        $this->assertFalse($order->tax_inclusive_snapshot);
        $this->assertSame('20.00', $order->taxable_amount);
        $this->assertSame('1.00', $order->tax_amount);
        $this->assertSame('21.00', $order->total_amount);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_order_snapshot_unaffected_after_settings_percent_changes(): void
    {
        $this->setTaxSettings(enabled: true, percent: '5.00', inclusive: false);

        $order = Order::factory()->create([
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'tax_enabled_snapshot' => true,
            'tax_label_snapshot' => 'GST',
            'tax_percent_snapshot' => '5.00',
            'tax_inclusive_snapshot' => false,
            'taxable_amount' => '100.00',
            'tax_amount' => '5.00',
            'total_amount' => '105.00',
        ]);

        $this->setTaxSettings(enabled: true, percent: '18.00', inclusive: false);

        $fromSnapshot = $this->app->make(TaxCalculatorInterface::class)->fromOrderSnapshot($order->fresh());

        $this->assertSame('5.00', $fromSnapshot->percent);
        $this->assertSame('5.00', $fromSnapshot->taxAmount);
        $this->assertSame('105.00', $fromSnapshot->cafeTotal);
        $this->assertSame('105.00', $order->fresh()->total_amount);
    }

    public function test_invoice_and_thermal_use_order_tax_snapshot_not_live_settings(): void
    {
        $this->setTaxSettings(enabled: true, percent: '18.00', inclusive: false, gstin: '29AAAAA0000A1Z5', legal: 'Legal Cafe Pvt Ltd');

        $order = Order::factory()->paymentConfirmed()->create([
            'subtotal' => '200.00',
            'discount_total' => '0.00',
            'tax_enabled_snapshot' => true,
            'tax_label_snapshot' => 'GST',
            'tax_percent_snapshot' => '5.00',
            'tax_inclusive_snapshot' => false,
            'taxable_amount' => '200.00',
            'tax_amount' => '10.00',
            'total_amount' => '210.00',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Filter Coffee',
            'line_subtotal' => '200.00',
        ]);

        $invoice = $this->app->make(OrderInvoiceServiceInterface::class)->build($order);

        $this->assertTrue($invoice->taxEnabled);
        $this->assertSame('5.00', $invoice->taxPercent);
        $this->assertSame('10.00', $invoice->taxAmount);
        $this->assertSame('210.00', $invoice->totalAmount);
        $this->assertSame('29AAAAA0000A1Z5', $invoice->gstin);
        $this->assertSame('Legal Cafe Pvt Ltd', $invoice->legalBusinessName);

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.invoice.print', $order))
            ->assertOk()
            ->assertSee('GST @ 5%', false)
            ->assertSee('Rs 10.00', false)
            ->assertSee('GSTIN: 29AAAAA0000A1Z5', false)
            ->assertSee('Legal Cafe Pvt Ltd', false)
            ->assertDontSee('18%', false);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.invoice.receipt', ['order' => $order, 'width' => 80]))
            ->assertOk()
            ->assertSee('GST 5%', false)
            ->assertSee('GSTIN 29AAAAA0000A1Z5', false);
    }

    public function test_gstin_and_legal_name_omitted_when_blank(): void
    {
        $this->setTaxSettings(enabled: true, percent: '5.00', inclusive: false, gstin: null, legal: null);

        $order = Order::factory()->paymentConfirmed()->create([
            'tax_enabled_snapshot' => true,
            'tax_label_snapshot' => 'GST',
            'tax_percent_snapshot' => '5.00',
            'tax_inclusive_snapshot' => false,
            'taxable_amount' => '50.00',
            'tax_amount' => '2.50',
            'subtotal' => '50.00',
            'total_amount' => '52.50',
        ]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        $invoice = $this->app->make(OrderInvoiceServiceInterface::class)->build($order);
        $this->assertNull($invoice->gstin);
        $this->assertNull($invoice->legalBusinessName);

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.invoice.print', $order))
            ->assertOk()
            ->assertDontSee('GSTIN', false);
    }

    public function test_api_order_exposes_customer_safe_tax_payload(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'tax_enabled_snapshot' => true,
            'tax_label_snapshot' => 'GST',
            'tax_percent_snapshot' => '5.00',
            'tax_inclusive_snapshot' => false,
            'taxable_amount' => '40.00',
            'tax_amount' => '2.00',
            'subtotal' => '40.00',
            'total_amount' => '42.00',
        ]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.tax.enabled', true)
            ->assertJsonPath('data.tax.label', 'GST')
            ->assertJsonPath('data.tax.percent', '5.00')
            ->assertJsonPath('data.tax.inclusive', false)
            ->assertJsonPath('data.tax.taxable_amount', '40.00')
            ->assertJsonPath('data.tax.amount', '2.00')
            ->assertJsonPath('data.total_amount', '42.00')
            ->assertJsonMissingPath('data.tax.gstin')
            ->assertJsonMissingPath('data.tax.cafe_total');
    }

    public function test_cart_summary_includes_server_tax_when_enabled(): void
    {
        $this->setTaxSettings(enabled: true, percent: '5.00', inclusive: false);

        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant('40.00');
        $cart = Cart::factory()->create(['customer_id' => $customer->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.cart.show'))
            ->assertOk()
            ->assertJsonPath('meta.summary.subtotal', '40.00')
            ->assertJsonPath('meta.summary.total', '42.00')
            ->assertJsonPath('meta.summary.tax.enabled', true)
            ->assertJsonPath('meta.summary.tax.amount', '2.00');
    }

    public function test_historical_order_without_tax_omits_gst_row_on_invoice(): void
    {
        $order = Order::factory()->paymentConfirmed()->create([
            'tax_enabled_snapshot' => false,
            'tax_amount' => '0.00',
            'subtotal' => '80.00',
            'total_amount' => '80.00',
        ]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        $invoice = $this->app->make(OrderInvoiceServiceInterface::class)->build($order);
        $this->assertFalse($invoice->taxEnabled);

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.invoice.print', $order))
            ->assertOk()
            ->assertDontSee('GST @', false);
    }

    public function test_production_structural_seed_does_not_enable_demo_gst(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertFalse(
            WebsiteSetting::query()
                ->where('key', WebsiteSettingKey::TaxEnabled->value)
                ->where('value', '1')
                ->exists(),
        );
    }

    public function test_demo_seed_enables_gst_deterministically(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(
            WebsiteSetting::query()
                ->where('key', WebsiteSettingKey::TaxEnabled->value)
                ->where('value', '1')
                ->exists(),
        );
        $this->assertTrue(
            WebsiteSetting::query()
                ->where('key', WebsiteSettingKey::TaxPercent->value)
                ->where('value', '5.00')
                ->exists(),
        );
        $this->assertTrue(
            Order::query()
                ->where('tax_enabled_snapshot', true)
                ->where('tax_percent_snapshot', '5.00')
                ->exists(),
        );
    }

    /**
     * @param  array{gstin?: ?string, legal?: ?string}  $extra
     */
    protected function setTaxSettings(
        bool $enabled,
        string $percent,
        bool $inclusive = false,
        ?string $gstin = null,
        ?string $legal = null,
    ): void {
        $map = [
            WebsiteSettingKey::TaxEnabled->value => $enabled ? '1' : '0',
            WebsiteSettingKey::TaxLabel->value => 'GST',
            WebsiteSettingKey::TaxPercent->value => $percent,
            WebsiteSettingKey::TaxInclusive->value => $inclusive ? '1' : '0',
            WebsiteSettingKey::TaxGstin->value => $gstin,
            WebsiteSettingKey::TaxLegalBusinessName->value => $legal,
        ];

        foreach ($map as $key => $value) {
            $enum = WebsiteSettingKey::from($key);
            WebsiteSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'section' => $enum->section(),
                    'value_type' => $enum->valueType(),
                    'value' => $value,
                ],
            );
        }
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
            'price' => $price,
            'is_available' => true,
            'is_active' => true,
        ]);
    }
}
