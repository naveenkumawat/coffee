<?php

namespace Tests\Feature;

use App\Enums\OperationalNotificationType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Events\Order\OrderPreparationStatusChanged;
use App\Models\OperationalNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPreparation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use App\Services\Realtime\RealtimePresenceServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimePresenceAndEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_authorize_presence_ops_channel_and_customer_cannot(): void
    {
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($barista, 'admin')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'presence-ops',
                'socket_id' => '1234.5678',
            ])
            ->assertOk();

        $this->actingAs($customer, 'web')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'presence-ops',
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_guest_denied_presence_and_heartbeat(): void
    {
        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-ops',
            'socket_id' => '1234.5678',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/realtime/presence/heartbeat')->assertUnauthorized();
    }

    public function test_customer_cannot_heartbeat_or_read_presence_summary(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/realtime/presence/heartbeat')->assertForbidden();
        $this->getJson('/api/v1/realtime/presence/summary')->assertForbidden();
    }

    public function test_operator_sees_presence_summary_counts_unique_users(): void
    {
        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $presence = app(RealtimePresenceServiceInterface::class);

        $presence->heartbeat($barista);
        $presence->heartbeat($barista);

        $this->actingAs($operator, 'admin')
            ->getJson('/api/v1/realtime/presence/summary')
            ->assertOk()
            ->assertJsonPath('data.roles.barista', 1)
            ->assertJsonPath('data.advisory', true);
    }

    public function test_bar_ticket_without_barista_online_escalates_to_operator_admin_once(): void
    {
        $owner = User::factory()->owner()->create();
        $operator = User::factory()->operator()->create();
        User::factory()->barista()->create();
        $chef = User::factory()->chef()->create();

        $order = $this->makePreparingOrder(PreparationStation::Bar);
        $ticket = OrderPreparation::query()->create([
            'order_id' => $order->id,
            'station' => PreparationStation::Bar,
            'status' => OrderPreparationStatus::Pending,
        ]);

        event(new OrderPreparationStatusChanged(
            $ticket->fresh(['order']),
            null,
            OrderPreparationStatus::Pending,
        ));
        event(new OrderPreparationStatusChanged(
            $ticket->fresh(['order']),
            null,
            OrderPreparationStatus::Pending,
        ));

        $escalations = OperationalNotification::query()
            ->where('type', OperationalNotificationType::EscalationNoBaristaOnline->value)
            ->where('subject_id', $ticket->id)
            ->get();

        $this->assertCount(1, $escalations);
        $this->assertTrue($escalations->first()->action_required);

        $ids = $escalations->first()->recipients->pluck('user_id')->all();
        $this->assertContains($owner->id, $ids);
        $this->assertContains($operator->id, $ids);
        $this->assertNotContains($chef->id, $ids);

        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::PreparationTicketPending->value)
                ->count(),
        );
    }

    public function test_kitchen_ticket_without_chef_online_escalates(): void
    {
        User::factory()->owner()->create();
        User::factory()->chef()->create();

        $order = $this->makePreparingOrder(PreparationStation::Kitchen);
        $ticket = OrderPreparation::query()->create([
            'order_id' => $order->id,
            'station' => PreparationStation::Kitchen,
            'status' => OrderPreparationStatus::Pending,
        ]);

        event(new OrderPreparationStatusChanged(
            $ticket->fresh(['order']),
            null,
            OrderPreparationStatus::Pending,
        ));

        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::EscalationNoChefOnline->value)
                ->count(),
        );
    }

    public function test_no_escalation_when_station_role_is_online_and_workflow_continues(): void
    {
        User::factory()->owner()->create();
        $barista = User::factory()->barista()->create();
        app(RealtimePresenceServiceInterface::class)->heartbeat($barista);

        $order = $this->makePreparingOrder(PreparationStation::Bar);
        $ticket = OrderPreparation::query()->create([
            'order_id' => $order->id,
            'station' => PreparationStation::Bar,
            'status' => OrderPreparationStatus::Pending,
        ]);

        event(new OrderPreparationStatusChanged(
            $ticket->fresh(['order']),
            null,
            OrderPreparationStatus::Pending,
        ));

        $this->assertSame(
            0,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::EscalationNoBaristaOnline->value)
                ->count(),
        );

        $prep = app(OrderPreparationServiceInterface::class);
        $prep->transition($ticket->fresh(), $barista, OrderPreparationStatus::Accepted);

        $this->assertSame(OrderPreparationStatus::Accepted, $ticket->fresh()->status);
    }

    public function test_escalation_resolves_when_staff_heartbeats_online(): void
    {
        User::factory()->owner()->create();
        $barista = User::factory()->barista()->create();

        $order = $this->makePreparingOrder(PreparationStation::Bar);
        $ticket = OrderPreparation::query()->create([
            'order_id' => $order->id,
            'station' => PreparationStation::Bar,
            'status' => OrderPreparationStatus::Pending,
        ]);

        event(new OrderPreparationStatusChanged(
            $ticket->fresh(['order']),
            null,
            OrderPreparationStatus::Pending,
        ));

        $this->assertNull(
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::EscalationNoBaristaOnline->value)
                ->first()
                ?->resolved_at,
        );

        $this->actingAs($barista, 'admin')
            ->postJson('/api/v1/realtime/presence/heartbeat')
            ->assertOk();

        $this->assertNotNull(
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::EscalationNoBaristaOnline->value)
                ->first()
                ?->resolved_at,
        );
    }

    public function test_barista_cannot_authorize_chef_presence_impersonation_via_role_channel(): void
    {
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-role.chef',
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    protected function makePreparingOrder(PreparationStation $station): Order
    {
        $customer = User::factory()->customer()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'preparation_station' => $station,
            'is_active' => true,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'is_active' => true,
        ]);
        Recipe::factory()->withDefaultLine()->create(['product_variant_id' => $variant->id]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Preparing,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_method' => PaymentMethod::Cash,
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'preparation_station' => $station,
            'product_name' => 'Item',
            'variant_name' => 'Regular',
            'unit_price' => '10.00',
            'quantity' => 1,
            'line_subtotal' => '10.00',
        ]);

        return $order->fresh('items');
    }
}
