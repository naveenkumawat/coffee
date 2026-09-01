<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPreparation;
use App\Models\User;
use App\Services\Order\OrderServiceInterface;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreparationPanelHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_access_orders_and_preparation_overview(): void
    {
        $operator = User::factory()->operator()->create();
        $order = $this->makeAcceptedOrderWithTickets();

        $this->actingAs($operator, 'admin')
            ->get(route('operator.orders.show', $order))
            ->assertOk()
            ->assertSee('Preparation Tickets', false)
            ->assertSee('Bar', false);

        $this->actingAs($operator, 'admin')
            ->get(route('operator.preparations.index'))
            ->assertOk()
            ->assertSee($order->order_number, false);
    }

    public function test_chef_can_transition_kitchen_ticket_via_http(): void
    {
        $chef = User::factory()->chef()->create();
        $order = $this->makeAcceptedOrderWithTickets();
        $kitchen = $order->preparations->firstWhere('station', PreparationStation::Kitchen);

        $this->actingAs($chef, 'admin')
            ->post(route('chef.preparations.accept', $kitchen))
            ->assertRedirect(route('chef.preparations.index'));

        $this->assertSame(OrderPreparationStatus::Accepted, $kitchen->fresh()->status);

        $this->actingAs($chef, 'admin')
            ->post(route('chef.preparations.preparing', $kitchen->fresh()))
            ->assertRedirect(route('chef.preparations.index'));

        $this->actingAs($chef, 'admin')
            ->post(route('chef.preparations.ready', $kitchen->fresh()))
            ->assertRedirect(route('chef.preparations.index'));

        $this->assertSame(OrderPreparationStatus::Ready, $kitchen->fresh()->status);
    }

    public function test_barista_cannot_transition_kitchen_ticket_via_http(): void
    {
        $barista = User::factory()->barista()->create();
        $order = $this->makeAcceptedOrderWithTickets();
        $kitchen = $order->preparations->firstWhere('station', PreparationStation::Kitchen);

        $this->actingAs($barista, 'admin')
            ->post(route('barista.preparations.accept', $kitchen))
            ->assertForbidden();
    }

    public function test_waiter_cannot_transition_preparation_tickets(): void
    {
        $waiter = User::factory()->waiter()->create();
        $order = $this->makeAcceptedOrderWithTickets();
        $bar = $order->preparations->firstWhere('station', PreparationStation::Bar);

        $this->actingAs($waiter, 'admin')
            ->post(route('barista.preparations.accept', $bar))
            ->assertForbidden();

        $this->actingAs($waiter, 'admin')
            ->post(route('chef.preparations.accept', $order->preparations->firstWhere('station', PreparationStation::Kitchen)))
            ->assertForbidden();
    }

    public function test_bar_and_kitchen_ready_syncs_order_ready_via_http(): void
    {
        $barista = User::factory()->barista()->create();
        $chef = User::factory()->chef()->create();
        $order = $this->makeAcceptedOrderWithTickets();
        $bar = $order->preparations->firstWhere('station', PreparationStation::Bar);
        $kitchen = $order->preparations->firstWhere('station', PreparationStation::Kitchen);

        foreach (['accept', 'preparing', 'ready'] as $action) {
            $this->actingAs($barista, 'admin')
                ->post(route('barista.preparations.'.$action, $bar->fresh()))
                ->assertRedirect(route('barista.preparations.index'));
        }

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);

        foreach (['accept', 'preparing', 'ready'] as $action) {
            $this->actingAs($chef, 'admin')
                ->post(route('chef.preparations.'.$action, $kitchen->fresh()))
                ->assertRedirect(route('chef.preparations.index'));
        }

        $this->assertSame(OrderStatus::ReadyForPickup, $order->fresh()->status);
        $this->assertTrue(
            $order->fresh('preparations')->preparations->every(
                fn (OrderPreparation $ticket): bool => $ticket->status === OrderPreparationStatus::Ready,
            ),
        );
    }

    protected function makeAcceptedOrderWithTickets(): Order
    {
        $operator = User::factory()->operator()->create();
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_method' => PaymentMethod::Manual,
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Latte',
            'preparation_station' => PreparationStation::Bar,
            'quantity' => 1,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Pasta',
            'preparation_station' => PreparationStation::Kitchen,
            'quantity' => 1,
        ]);

        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus(OrderStatus::Accepted->value);

        return app(OrderServiceInterface::class)
            ->transition($order, $operator, $transfer)
            ->fresh(['preparations', 'items']);
    }
}
