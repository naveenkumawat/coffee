<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPreparation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use App\Transfers\Order\OrderTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderPreparationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_order_creates_station_tickets_from_item_snapshots(): void
    {
        $operator = User::factory()->operator()->create();
        $order = $this->makeAcceptedReadyOrder(
            items: [
                ['station' => PreparationStation::Bar, 'name' => 'Latte'],
                ['station' => PreparationStation::Kitchen, 'name' => 'Pasta'],
                ['station' => PreparationStation::Kitchen, 'name' => 'Fries'],
            ],
            status: OrderStatus::PaymentConfirmed,
        );

        $updated = app(OrderServiceInterface::class)->transition(
            $order,
            $operator,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $this->assertSame(OrderStatus::Accepted, $updated->status);
        $this->assertSame(2, $updated->preparations()->count());
        $this->assertTrue(
            $updated->preparations->every(
                fn (OrderPreparation $ticket): bool => $ticket->status === OrderPreparationStatus::Pending,
            ),
        );

        $barTicket = $updated->preparations->firstWhere('station', PreparationStation::Bar);
        $kitchenTicket = $updated->preparations->firstWhere('station', PreparationStation::Kitchen);

        $this->assertNotNull($barTicket);
        $this->assertNotNull($kitchenTicket);
        $this->assertCount(1, $barTicket->items());
        $this->assertCount(2, $kitchenTicket->items());
        $this->assertSame('Latte', $barTicket->items()->first()->product_name);
    }

    public function test_store_does_not_create_tickets_while_pending_payment(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makeVariant(PreparationStation::Bar, 'Latte');

        $transfer = app(OrderTransferInterface::class);
        $transfer->setCustomerId($customer->id);
        $transfer->setCustomerName($customer->name);
        $transfer->setCustomerEmail($customer->email);
        $transfer->setCustomerPhone($customer->phone);
        $transfer->setPickupName($customer->name);
        $transfer->setPickupPhone($customer->phone);
        $transfer->setFulfilmentMethod(OrderFulfilmentMethod::Takeaway->value);
        $transfer->setPaymentMethod(PaymentMethod::Manual->apiKey());
        $transfer->setItems([
            ['product_variant_id' => $variant->id, 'quantity' => 1],
        ]);

        $order = app(OrderServiceInterface::class)->store($customer, $transfer);

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(0, $order->preparations()->count());
        $this->assertSame(PreparationStation::Bar, $order->items->first()->preparation_station);
    }

    public function test_mixed_order_ready_only_when_all_stations_ready(): void
    {
        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $chef = User::factory()->chef()->create();
        $order = $this->makeAcceptedReadyOrder([
            ['station' => PreparationStation::Bar, 'name' => 'Latte'],
            ['station' => PreparationStation::Kitchen, 'name' => 'Pasta'],
        ]);

        app(OrderServiceInterface::class)->transition(
            $order,
            $operator,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $order = $order->fresh('preparations');
        $bar = $order->preparations->firstWhere('station', PreparationStation::Bar);
        $kitchen = $order->preparations->firstWhere('station', PreparationStation::Kitchen);
        $preparations = app(OrderPreparationServiceInterface::class);

        $preparations->transition($bar, $barista, OrderPreparationStatus::Accepted);
        $preparations->transition($bar->fresh(), $barista, OrderPreparationStatus::Preparing);
        $preparations->transition($bar->fresh(), $barista, OrderPreparationStatus::Ready);

        $order = $order->fresh(['preparations']);
        $this->assertSame(OrderStatus::Preparing, $order->status);
        $this->assertSame(OrderPreparationStatus::Ready, $order->preparations->firstWhere('station', PreparationStation::Bar)->status);
        $this->assertNotSame(OrderPreparationStatus::Ready, $order->preparations->firstWhere('station', PreparationStation::Kitchen)->status);

        $preparations->transition($kitchen->fresh(), $chef, OrderPreparationStatus::Accepted);
        $preparations->transition($kitchen->fresh(), $chef, OrderPreparationStatus::Preparing);
        $preparations->transition($kitchen->fresh(), $chef, OrderPreparationStatus::Ready);

        $order = $order->fresh(['preparations']);
        $this->assertSame(OrderStatus::ReadyForPickup, $order->status);
        $this->assertTrue(
            $order->preparations->every(
                fn (OrderPreparation $ticket): bool => $ticket->status === OrderPreparationStatus::Ready,
            ),
        );
    }

    public function test_operator_cannot_transition_preparation_tickets(): void
    {
        $operator = User::factory()->operator()->create();
        $order = $this->makeAcceptedReadyOrder([
            ['station' => PreparationStation::Bar, 'name' => 'Latte'],
        ]);

        app(OrderServiceInterface::class)->transition(
            $order,
            $operator,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $ticket = $order->fresh('preparations')->preparations->first();

        $this->expectException(ValidationException::class);

        app(OrderPreparationServiceInterface::class)->transition(
            $ticket,
            $operator,
            OrderPreparationStatus::Accepted,
        );
    }

    public function test_barista_cannot_transition_kitchen_ticket(): void
    {
        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $order = $this->makeAcceptedReadyOrder([
            ['station' => PreparationStation::Kitchen, 'name' => 'Pasta'],
        ]);

        app(OrderServiceInterface::class)->transition(
            $order,
            $operator,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $ticket = $order->fresh('preparations')->preparations->first();

        $this->expectException(ValidationException::class);

        app(OrderPreparationServiceInterface::class)->transition(
            $ticket,
            $barista,
            OrderPreparationStatus::Accepted,
        );
    }

    public function test_cancelling_order_cancels_active_tickets(): void
    {
        $admin = User::factory()->owner()->create();
        $order = $this->makeAcceptedReadyOrder([
            ['station' => PreparationStation::Bar, 'name' => 'Latte'],
            ['station' => PreparationStation::Kitchen, 'name' => 'Pasta'],
        ]);

        app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $updated = app(OrderServiceInterface::class)->transition(
            $order->fresh(),
            $admin,
            $this->statusTransfer(OrderStatus::Cancelled),
        );

        $this->assertSame(OrderStatus::Cancelled, $updated->status);
        $this->assertTrue(
            $updated->preparations->every(
                fn (OrderPreparation $ticket): bool => $ticket->status === OrderPreparationStatus::Cancelled,
            ),
        );
    }

    public function test_dining_round_stays_pending_until_accepted(): void
    {
        $waiter = User::factory()->waiter()->create();
        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = DiningSession::query()->create([
            'session_number' => 'DS-TEST-1',
            'cafe_table_id' => $table->id,
            'customer_id' => $customer->id,
            'opened_by_user_id' => $waiter->id,
            'status' => DiningSessionStatus::Open->value,
            'guest_count' => 2,
            'table_name_snapshot' => $table->snapshotLabel(),
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'opened_at' => now(),
            'payment_status' => PaymentStatus::Pending->value,
            'subtotal_amount' => '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'taxable_amount' => '0.00',
            'total_amount' => '0.00',
        ]);

        $bar = $this->makeVariant(PreparationStation::Bar, 'Latte');
        $kitchen = $this->makeVariant(PreparationStation::Kitchen, 'Pasta');

        $order = app(OrderServiceInterface::class)->placeDiningRound($waiter, $session, [
            ['product_variant_id' => $bar->id, 'quantity' => 1],
            ['product_variant_id' => $kitchen->id, 'quantity' => 1],
        ]);

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(0, $order->preparations()->count());

        $accepted = app(DiningSessionServiceInterface::class)
            ->acceptRound($session, $order, $waiter);

        $this->assertSame(OrderStatus::Accepted, $accepted->status);
        $this->assertSame(2, $accepted->preparations()->count());
        $this->assertTrue(
            $accepted->preparations->every(
                fn (OrderPreparation $ticket): bool => $ticket->status === OrderPreparationStatus::Pending,
            ),
        );
    }

    /**
     * @param  list<array{station: PreparationStation, name: string}>  $items
     */
    protected function makeAcceptedReadyOrder(array $items, OrderStatus $status = OrderStatus::PaymentConfirmed): Order
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_method' => PaymentMethod::Manual,
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_confirmed_at' => now(),
        ]);

        foreach ($items as $item) {
            $variant = $this->makeVariant($item['station'], $item['name']);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'recipe_id' => $variant->recipe?->id,
                'preparation_station' => $item['station'],
                'product_name' => $item['name'],
                'variant_name' => 'Regular',
                'unit_price' => '10.00',
                'quantity' => 1,
                'line_subtotal' => '10.00',
            ]);
        }

        return $order->fresh('items');
    }

    protected function makeVariant(PreparationStation $station, string $name): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => $station,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => '10.00',
            'is_active' => true,
            'is_available' => true,
        ]);

        Recipe::factory()->withDefaultLine()->create([
            'product_variant_id' => $variant->id,
        ]);

        return $variant->fresh('recipe');
    }

    protected function statusTransfer(OrderStatus $status): OrderStatusTransitionTransfer
    {
        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus($status->value);
        $transfer->setNotes(null);

        return $transfer;
    }
}
