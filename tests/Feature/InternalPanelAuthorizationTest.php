<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalPanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_barista_cannot_open_administrator_config_and_user_routes(): void
    {
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin');

        $this->get(route('administrator.dashboard'))->assertForbidden();
        $this->get(route('administrator.users.index'))->assertForbidden();
        $this->get(route('administrator.website-settings.edit'))->assertForbidden();
        $this->get(route('administrator.promotions.index'))->assertForbidden();
    }

    public function test_waiter_cannot_open_administrator_or_barista_operational_routes(): void
    {
        $waiter = User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);

        $this->actingAs($waiter, 'admin');

        $this->get(route('administrator.dashboard'))->assertForbidden();
        $this->get(route('administrator.users.index'))->assertForbidden();
        $this->get(route('administrator.website-settings.edit'))->assertForbidden();
        $this->get(route('administrator.promotions.index'))->assertForbidden();
        $this->get(route('barista.dashboard'))->assertForbidden();
        $this->get(route('barista.preparations.index'))->assertForbidden();
        $this->get(route('barista.inventory.index'))->assertForbidden();
        $this->get(route('operator.dashboard'))->assertForbidden();
        $this->get(route('chef.dashboard'))->assertForbidden();
    }

    public function test_customer_cannot_open_internal_panels(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'web');

        $this->get(route('administrator.dashboard'))->assertRedirect();
        $this->get(route('operator.dashboard'))->assertRedirect();
        $this->get(route('barista.dashboard'))->assertRedirect();
        $this->get(route('chef.dashboard'))->assertRedirect();
        $this->get(route('waiter.dashboard'))->assertRedirect();
    }

    public function test_barista_order_show_is_read_only_without_invoice_or_status_actions(): void
    {
        $barista = User::factory()->barista()->create();
        $order = $this->makePaidOrder();

        $this->actingAs($barista, 'admin')
            ->get(route('barista.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Invoice', false)
            ->assertDontSee('Production Cost', false)
            ->assertDontSee('Margin', false)
            ->assertDontSee(route('administrator.orders.payment-proof.reject', $order), false)
            ->assertDontSee(route('operator.orders.invoice.print', $order), false);
    }

    protected function makePaidOrder(): Order
    {
        $order = Order::factory()->paymentConfirmed()->create([
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal' => '40.00',
            'total_amount' => '40.00',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Filter Coffee',
            'line_subtotal' => '40.00',
            'unit_price' => '40.00',
            'quantity' => 1,
        ]);

        return $order->fresh(['items']);
    }
}
