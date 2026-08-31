<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Invoice\OrderInvoiceServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_download_own_eligible_invoice_pdf(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::BusinessName->value],
            [
                'section' => WebsiteSettingKey::BusinessName->section(),
                'value' => 'The88Coffees',
                'value_type' => WebsiteSettingKey::BusinessName->valueType(),
            ],
        );

        $customer = User::factory()->customer()->create();
        $order = $this->makePaidOrder($customer, [
            'order_number' => 'CC-310826-0026',
            'customer_name' => 'Invoice Owner',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Snapshot Latte',
            'variant_name' => 'Large',
            'unit_price' => '150.00',
            'quantity' => 2,
            'line_subtotal' => '300.00',
        ]);

        $order->update([
            'subtotal' => '300.00',
            'total_amount' => '300.00',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->get(route('api.v1.orders.invoice.download', $order));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'The88Coffees-CC-310826-0026.pdf',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertGreaterThan(100, strlen($response->getContent()));
    }

    public function test_customer_cannot_download_another_customers_invoice(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $order = $this->makePaidOrder($owner);

        Sanctum::actingAs($other);

        $this->get(route('api.v1.orders.invoice.download', $order))
            ->assertForbidden();
    }

    public function test_unauthenticated_customer_invoice_download_is_denied(): void
    {
        $order = $this->makePaidOrder(User::factory()->customer()->create());

        $this->getJson(route('api.v1.orders.invoice.download', $order))
            ->assertUnauthorized();
    }

    public function test_invoice_uses_order_snapshots_and_not_live_product_or_customer_changes(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Live Name',
            'email' => 'live@example.com',
        ]);

        $order = $this->makePaidOrder($customer, [
            'customer_name' => 'Snapshot Customer',
            'customer_email' => 'snapshot@example.com',
            'customer_phone' => '9000000001',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Historical Mocha',
            'variant_name' => 'Regular',
            'unit_price' => '180.00',
            'quantity' => 1,
            'line_subtotal' => '180.00',
        ]);

        $order->update([
            'subtotal' => '180.00',
            'total_amount' => '180.00',
        ]);

        $customer->update(['name' => 'Changed Name', 'email' => 'changed@example.com']);

        $invoice = app(OrderInvoiceServiceInterface::class)->build($order->fresh(['items']));

        $this->assertSame('Snapshot Customer', $invoice->customerName);
        $this->assertSame('snapshot@example.com', $invoice->customerEmail);
        $this->assertSame('Historical Mocha', $invoice->lines[0]['product_name']);
        $this->assertSame('180.00', $invoice->totalAmount);
        $this->assertStringNotContainsString('Changed Name', json_encode($invoice->toArray()));
    }

    public function test_dine_in_and_delivery_snapshots_render_on_admin_print_views(): void
    {
        $manager = User::factory()->manager()->create();
        $table = CafeTable::factory()->create(['name' => 'T7']);

        $dineIn = $this->makePaidOrder(User::factory()->customer()->create(), [
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
            'cafe_table_id' => $table->id,
            'table_name_snapshot' => 'T7',
            'subtotal' => '100.00',
            'total_amount' => '100.00',
        ]);
        OrderItem::factory()->create([
            'order_id' => $dineIn->id,
            'product_name' => 'Table Latte',
            'line_subtotal' => '100.00',
            'unit_price' => '100.00',
        ]);

        $delivery = $this->makePaidOrder(User::factory()->customer()->create(), [
            'fulfilment_method' => OrderFulfilmentMethod::Delivery,
            'delivery_contact_name' => 'Door Person',
            'delivery_phone' => '9111111111',
            'delivery_address' => '12 Snapshot Street',
            'delivery_fee_amount' => '40.00',
            'subtotal' => '200.00',
            'total_amount' => '240.00',
        ]);
        OrderItem::factory()->create([
            'order_id' => $delivery->id,
            'product_name' => 'Delivery Mocha',
            'unit_price' => '200.00',
            'line_subtotal' => '200.00',
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.invoice.print', $dineIn))
            ->assertOk()
            ->assertSee('DINE-IN', false)
            ->assertSee('TABLE T7', false)
            ->assertSee('Table Latte', false)
            ->assertDontSee('Production Cost', false)
            ->assertDontSee('payment_proof', false)
            ->assertDontSee('ingredient cost', false);

        $dineInvoice = app(OrderInvoiceServiceInterface::class)->build($dineIn->fresh(['items']));
        $payload = strtolower(json_encode($dineInvoice->toArray()) ?: '');
        $this->assertStringNotContainsString('recipe', $payload);
        $this->assertStringNotContainsString('margin', $payload);
        $this->assertStringNotContainsString('profit', $payload);
        $this->assertStringNotContainsString('ingredient', $payload);
        $this->assertStringNotContainsString('payment_proof', $payload);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.invoice.receipt', ['order' => $delivery, 'width' => 58]))
            ->assertOk()
            ->assertSee('DELIVERY', false)
            ->assertSee('12 Snapshot Street', false)
            ->assertSee('Door Person', false)
            ->assertSee('Rs 40.00', false)
            ->assertSee('Rs 240.00', false)
            ->assertSee('58mm', false);
    }

    public function test_item_and_total_calculations_match_stored_order(): void
    {
        $order = $this->makePaidOrder(User::factory()->customer()->create(), [
            'subtotal' => '250.00',
            'discount_total' => '10.00',
            'delivery_fee_amount' => '30.00',
            'total_amount' => '270.00',
            'fulfilment_method' => OrderFulfilmentMethod::Delivery,
            'delivery_address' => 'Addr',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'A',
            'unit_price' => '100.00',
            'quantity' => 1,
            'line_subtotal' => '100.00',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'B',
            'unit_price' => '75.00',
            'quantity' => 2,
            'line_subtotal' => '150.00',
        ]);

        $invoice = app(OrderInvoiceServiceInterface::class)->build($order->fresh(['items']));

        $this->assertCount(2, $invoice->lines);
        $this->assertSame('250.00', $invoice->subtotal);
        $this->assertSame('10.00', $invoice->discountTotal);
        $this->assertSame('30.00', $invoice->deliveryFeeAmount);
        $this->assertSame('270.00', $invoice->totalAmount);
        $this->assertSame('150.00', $invoice->lines[1]['line_total']);
    }

    public function test_admin_invoice_routes_authorized_and_barista_denied(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $order = $this->makePaidOrder(User::factory()->customer()->create());
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.invoice.pdf', $order))
            ->assertOk();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.invoice.print', $order))
            ->assertOk()
            ->assertSee($order->order_number, false);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.invoice.receipt', ['order' => $order, 'width' => 80]))
            ->assertOk()
            ->assertSee('80mm', false);

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.orders.invoice.print', $order))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.orders.invoice.receipt', ['order' => $order, 'width' => 80]))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.orders.invoice.pdf', $order))
            ->assertForbidden();
    }

    public function test_admin_order_show_exposes_invoice_actions_for_managers_and_owners(): void
    {
        $manager = User::factory()->manager()->create();
        $owner = User::factory()->owner()->create();
        $order = $this->makePaidOrder(User::factory()->customer()->create());
        OrderItem::factory()->create(['order_id' => $order->id]);

        foreach ([$manager, $owner] as $user) {
            $this->actingAs($user, 'admin')
                ->get(route('administrator.orders.show', $order))
                ->assertOk()
                ->assertSee('Invoice', false)
                ->assertSee('Print A4 Invoice', false)
                ->assertSee('Print 80mm Receipt', false)
                ->assertSee('Print 58mm Receipt', false)
                ->assertSee('Download PDF', false)
                ->assertSee(route('administrator.orders.invoice.print', $order), false)
                ->assertSee(route('administrator.orders.invoice.pdf', $order), false)
                ->assertDontSee('<x-internal.button', false);
        }
    }

    public function test_barista_order_show_does_not_expose_administrator_invoice_actions(): void
    {
        $barista = User::factory()->barista()->create();
        $order = $this->makePaidOrder(User::factory()->customer()->create());
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.orders.show', $order))
            ->assertForbidden();
    }

    public function test_invoice_route_names_resolve(): void
    {
        $order = $this->makePaidOrder(User::factory()->customer()->create());

        $this->assertSame(
            url("/api/v1/orders/{$order->id}/invoice"),
            route('api.v1.orders.invoice.download', $order),
        );
        $this->assertSame(
            url("/administrator/orders/{$order->id}/invoice/pdf"),
            route('administrator.orders.invoice.pdf', $order),
        );
        $this->assertSame(
            url("/administrator/orders/{$order->id}/invoice/print"),
            route('administrator.orders.invoice.print', $order),
        );
        $this->assertSame(
            url("/administrator/orders/{$order->id}/invoice/receipt?width=80"),
            route('administrator.orders.invoice.receipt', ['order' => $order, 'width' => 80]),
        );
    }

    public function test_order_resource_exposes_invoice_available_flag(): void
    {
        $customer = User::factory()->customer()->create();
        $eligible = $this->makePaidOrder($customer);
        $completed = $this->makePaidOrder($customer, [
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);
        $pending = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.orders.show', $eligible))
            ->assertOk()
            ->assertJsonPath('data.invoice_available', true);

        $this->getJson(route('api.v1.orders.show', $completed))
            ->assertOk()
            ->assertJsonPath('data.invoice_available', true);

        $this->getJson(route('api.v1.orders.show', $pending))
            ->assertOk()
            ->assertJsonPath('data.invoice_available', false);

        $this->get(route('api.v1.orders.invoice.download', $pending))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makePaidOrder(User $customer, array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_confirmed_at' => now(),
            'subtotal' => '120.00',
            'discount_total' => '0.00',
            'total_amount' => '120.00',
        ], $overrides));
    }
}
