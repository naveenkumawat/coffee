<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPreparation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use App\Services\Reporting\OperationalPerformanceReportingService;
use App\Services\Reporting\OperationalPerformanceReportingServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalPerformanceReportingPhaseF33Test extends TestCase
{
    use RefreshDatabase;

    public function test_bar_and_kitchen_timing_metrics_and_missing_timestamps_excluded(): void
    {
        $bar = $this->makeTicket(
            station: PreparationStation::Bar,
            status: OrderPreparationStatus::Ready,
            createdAt: now()->subMinutes(10),
            acceptedAt: now()->subMinutes(8),
            preparingAt: now()->subMinutes(7),
            readyAt: now()->subMinutes(2),
        );
        $kitchen = $this->makeTicket(
            station: PreparationStation::Kitchen,
            status: OrderPreparationStatus::Ready,
            createdAt: now()->subMinutes(12),
            acceptedAt: now()->subMinutes(11),
            preparingAt: now()->subMinutes(10),
            readyAt: now()->subMinutes(4),
        );
        $incomplete = $this->makeTicket(
            station: PreparationStation::Bar,
            status: OrderPreparationStatus::Preparing,
            createdAt: now()->subMinutes(5),
            acceptedAt: now()->subMinutes(4),
            preparingAt: now()->subMinutes(3),
            readyAt: null,
        );

        $report = app(OperationalPerformanceReportingServiceInterface::class)->buildAdminReport([
            'preset' => OperationalPerformanceReportingService::PRESET_TODAY,
        ]);

        $barSummary = $report['stations'][PreparationStation::Bar->value];
        $kitchenSummary = $report['stations'][PreparationStation::Kitchen->value];

        $this->assertSame(2, $barSummary['tickets']);
        $this->assertSame(1, $barSummary['ready_tickets']);
        $this->assertSame(120, $barSummary['avg_queue_wait_seconds']);
        $this->assertSame(60, $barSummary['avg_start_delay_seconds']);
        $this->assertSame(300, $barSummary['avg_preparation_seconds']);
        $this->assertSame(480, $barSummary['avg_total_ticket_seconds']);

        $this->assertSame(1, $kitchenSummary['ready_tickets']);
        $this->assertSame(60, $kitchenSummary['avg_queue_wait_seconds']);
        $this->assertSame(360, $kitchenSummary['avg_preparation_seconds']);

        $metrics = app(OperationalPerformanceReportingService::class)->ticketMetrics($incomplete);
        $this->assertNull($metrics['prep_seconds']);
        $this->assertNull($metrics['total_seconds']);
        $this->assertSame(60, $metrics['queue_wait_seconds']);

        // Ensure incomplete ready_at does not zero-fill averages.
        $this->assertNotSame(0, $barSummary['avg_preparation_seconds']);
    }

    public function test_repeated_transition_does_not_overwrite_timestamps(): void
    {
        $barista = User::factory()->barista()->create();
        $ticket = $this->makeTicket(
            station: PreparationStation::Bar,
            status: OrderPreparationStatus::Pending,
            createdAt: now()->subMinutes(5),
        );

        $service = app(OrderPreparationServiceInterface::class);
        $service->transition($ticket, $barista, OrderPreparationStatus::Accepted);
        $acceptedAt = $ticket->fresh()->accepted_at;

        $this->travel(2)->minutes();
        // Force invalid path protection: accepted ticket cannot re-accept; simulate preserve via preparing then check accepted stays.
        $service->transition($ticket->fresh(), $barista, OrderPreparationStatus::Preparing);
        $this->assertEquals($acceptedAt?->toDateTimeString(), $ticket->fresh()->accepted_at?->toDateTimeString());

        $preparingAt = $ticket->fresh()->preparing_at;
        $this->travel(1)->minutes();
        $service->transition($ticket->fresh(), $barista, OrderPreparationStatus::Ready);
        $this->assertEquals($preparingAt?->toDateTimeString(), $ticket->fresh()->preparing_at?->toDateTimeString());
        $this->assertNotNull($ticket->fresh()->ready_at);
    }

    public function test_mixed_order_overall_ready_uses_latest_station_and_gap(): void
    {
        $order = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'status' => OrderStatus::Preparing,
            'placed_at' => now()->subMinutes(20),
            'accepted_at' => now()->subMinutes(18),
        ]);

        $this->attachTicket($order, PreparationStation::Bar, OrderPreparationStatus::Ready, [
            'created_at' => now()->subMinutes(18),
            'accepted_at' => now()->subMinutes(17),
            'preparing_at' => now()->subMinutes(16),
            'ready_at' => now()->subMinutes(10),
        ]);
        $this->attachTicket($order, PreparationStation::Kitchen, OrderPreparationStatus::Preparing, [
            'created_at' => now()->subMinutes(18),
            'accepted_at' => now()->subMinutes(17),
            'preparing_at' => now()->subMinutes(15),
            'ready_at' => null,
        ]);

        $report = app(OperationalPerformanceReportingServiceInterface::class)->buildAdminReport([
            'preset' => OperationalPerformanceReportingService::PRESET_TODAY,
        ]);
        $row = collect($report['mixed_orders']['rows'])->firstWhere('order_number', $order->order_number);
        $this->assertFalse($row['all_stations_ready']);
        $this->assertNull($row['overall_ready_at']);
        $this->assertSame(PreparationStation::Kitchen->value, $row['blocking_station']);

        OrderPreparation::query()
            ->where('order_id', $order->id)
            ->where('station', PreparationStation::Kitchen)
            ->update([
                'status' => OrderPreparationStatus::Ready->value,
                'ready_at' => now()->subMinutes(4),
                'completed_at' => now()->subMinutes(4),
            ]);

        $report2 = app(OperationalPerformanceReportingServiceInterface::class)->buildAdminReport([
            'preset' => OperationalPerformanceReportingService::PRESET_TODAY,
        ]);
        $row2 = collect($report2['mixed_orders']['rows'])->firstWhere('order_number', $order->order_number);
        $this->assertTrue($row2['all_stations_ready']);
        $this->assertSame(360, $row2['station_gap_seconds']);
        $this->assertSame(PreparationStation::Kitchen->value, $row2['blocking_station']);
    }

    public function test_retail_turnaround_excludes_cancelled_from_successful_averages(): void
    {
        $completed = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'dining_session_id' => null,
            'status' => OrderStatus::Completed,
            'placed_at' => now()->subMinutes(30),
            'accepted_at' => now()->subMinutes(25),
            'ready_for_pickup_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
        ]);
        $this->attachTicket($completed, PreparationStation::Bar, OrderPreparationStatus::Ready, [
            'created_at' => now()->subMinutes(25),
            'accepted_at' => now()->subMinutes(24),
            'preparing_at' => now()->subMinutes(23),
            'ready_at' => now()->subMinutes(10),
        ]);

        $cancelled = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'dining_session_id' => null,
            'status' => OrderStatus::Cancelled,
            'placed_at' => now()->subMinutes(40),
            'accepted_at' => now()->subMinutes(39),
            'cancelled_at' => now()->subMinutes(38),
            'ready_for_pickup_at' => null,
            'completed_at' => null,
        ]);
        $this->attachTicket($cancelled, PreparationStation::Bar, OrderPreparationStatus::Cancelled, [
            'created_at' => now()->subMinutes(39),
            'accepted_at' => now()->subMinutes(39),
            'preparing_at' => null,
            'ready_at' => null,
            'cancelled_at' => now()->subMinutes(38),
        ]);

        $report = app(OperationalPerformanceReportingServiceInterface::class)->buildAdminReport([
            'preset' => OperationalPerformanceReportingService::PRESET_TODAY,
        ]);

        $this->assertSame(300, $report['retail_turnaround']['averages']['order_to_accept_seconds']);
        $this->assertSame(900, $report['retail_turnaround']['averages']['accept_to_ready_seconds']);
        $this->assertSame(1, count($report['retail_turnaround']['rows']));
        $this->assertSame(1, $report['cancellations']['before_preparation']);
    }

    public function test_dining_round_and_session_metrics(): void
    {
        $session = $this->makeDiningSession(
            status: DiningSessionStatus::Closed,
            openedAt: now()->subHours(2),
            billRequestedAt: now()->subMinutes(40),
            paidAt: now()->subMinutes(30),
            closedAt: now()->subMinutes(25),
        );

        $round1 = $this->makeDiningRound($session, 1, now()->subMinutes(100));
        $this->attachTicket($round1, PreparationStation::Bar, OrderPreparationStatus::Ready, [
            'created_at' => now()->subMinutes(100),
            'accepted_at' => now()->subMinutes(99),
            'preparing_at' => now()->subMinutes(98),
            'ready_at' => now()->subMinutes(90),
        ]);
        $this->attachTicket($round1, PreparationStation::Kitchen, OrderPreparationStatus::Ready, [
            'created_at' => now()->subMinutes(100),
            'accepted_at' => now()->subMinutes(99),
            'preparing_at' => now()->subMinutes(97),
            'ready_at' => now()->subMinutes(85),
        ]);

        $round2 = $this->makeDiningRound($session, 2, now()->subMinutes(70));
        $this->attachTicket($round2, PreparationStation::Bar, OrderPreparationStatus::Ready, [
            'created_at' => now()->subMinutes(70),
            'accepted_at' => now()->subMinutes(69),
            'preparing_at' => now()->subMinutes(68),
            'ready_at' => now()->subMinutes(60),
        ]);

        $openSession = $this->makeDiningSession(
            status: DiningSessionStatus::Open,
            openedAt: now()->subMinutes(15),
        );

        $report = app(OperationalPerformanceReportingServiceInterface::class)->buildAdminReport([
            'preset' => OperationalPerformanceReportingService::PRESET_TODAY,
        ]);

        $roundRow = collect($report['dining_rounds']['rows'])->firstWhere('order_number', $round1->order_number);
        $this->assertSame(900, $roundRow['round_to_ready_seconds']);
        $this->assertSame(300, $roundRow['station_gap_seconds']);

        $sessionRow = collect($report['dining_sessions']['rows'])->firstWhere('session_number', $session->session_number);
        $this->assertSame(2, $sessionRow['round_count']);
        $this->assertSame(5700, $sessionRow['session_duration_seconds']);
        $this->assertSame(600, $sessionRow['bill_request_to_payment_seconds']);
        $this->assertSame(300, $sessionRow['payment_to_close_seconds']);
        $this->assertSame(1800, $sessionRow['avg_round_interval_seconds']);

        $openRow = collect($report['dining_sessions']['rows'])->firstWhere('session_number', $openSession->session_number);
        $this->assertSame(0, $openRow['round_count']);
        $this->assertNull($openRow['session_duration_seconds']);
        $this->assertNull($openRow['bill_request_to_payment_seconds']);
    }

    public function test_live_operator_overview_and_staff_scopes(): void
    {
        $this->makeTicket(
            station: PreparationStation::Bar,
            status: OrderPreparationStatus::Pending,
            createdAt: now()->subMinutes(9),
        );
        $this->makeTicket(
            station: PreparationStation::Kitchen,
            status: OrderPreparationStatus::Preparing,
            createdAt: now()->subMinutes(8),
            acceptedAt: now()->subMinutes(7),
            preparingAt: now()->subMinutes(6),
        );

        $overview = app(OperationalPerformanceReportingServiceInterface::class)->buildOperatorOverview();
        $this->assertGreaterThanOrEqual(1, $overview['bar']['pending']);
        $this->assertGreaterThanOrEqual(1, $overview['kitchen']['preparing']);
        $this->assertNotNull($overview['bar']['oldest_active']);
        $this->assertArrayNotHasKey('gross_paid_sales', $overview);

        $barContext = app(OperationalPerformanceReportingServiceInterface::class)
            ->buildStationQueueContext(PreparationStation::Bar->value);
        $this->assertSame(PreparationStation::Bar->value, $barContext['station']);
        $this->assertNotEmpty($barContext['tickets']);
        $this->assertArrayHasKey('queue_age_seconds', $barContext['tickets'][0]);
    }

    public function test_auth_roles_and_no_financial_leakage(): void
    {
        $manager = User::factory()->manager()->create();
        $operator = User::factory()->operator()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.operational-performance.index', ['preset' => 'today']))
            ->assertOk()
            ->assertSee('Operational Performance')
            ->assertDontSee('Gross Paid Sales');

        $this->actingAs($operator, 'admin')
            ->get(route('operator.reports.operational-performance.index'))
            ->assertOk()
            ->assertSee('Operational Performance')
            ->assertDontSee('Export Preparations CSV')
            ->assertDontSee('Gross Paid Sales');

        $this->actingAs($operator, 'admin')
            ->get(route('administrator.reports.operational-performance.index'))
            ->assertForbidden();

        foreach ([
            User::factory()->barista()->create(),
            User::factory()->chef()->create(),
            User::factory()->waiter()->create(),
        ] as $user) {
            $this->actingAs($user, 'admin')
                ->get(route('administrator.reports.operational-performance.index'))
                ->assertForbidden();
            $this->actingAs($user, 'admin')
                ->get(route('operator.reports.operational-performance.index'))
                ->assertForbidden();
        }

        $waiter = User::factory()->waiter()->create();
        $session = $this->makeDiningSession(DiningSessionStatus::BillingRequested, now()->subHour(), billRequestedAt: now()->subMinutes(12));
        $this->actingAs($waiter, 'admin')
            ->get(route('waiter.sessions.show', $session))
            ->assertOk()
            ->assertSee('Bill requested elapsed')
            ->assertDontSee('Gross Paid Sales')
            ->assertDontSee('Avg BAR Prep');
    }

    public function test_csv_matches_service_and_timezone_boundaries(): void
    {
        $timezone = app(CafeAvailabilityServiceInterface::class)->timezone();
        $yesterday = CarbonImmutable::now($timezone)->subDay()->setTime(11, 0)->setTimezone('UTC');
        $today = CarbonImmutable::now($timezone)->setTime(11, 0)->setTimezone('UTC');

        $this->makeTicket(
            station: PreparationStation::Bar,
            status: OrderPreparationStatus::Ready,
            createdAt: $yesterday->subMinutes(6),
            acceptedAt: $yesterday->subMinutes(5),
            preparingAt: $yesterday->subMinutes(4),
            readyAt: $yesterday,
            placedAt: $yesterday->subMinutes(6),
        );
        $todayTicket = $this->makeTicket(
            station: PreparationStation::Bar,
            status: OrderPreparationStatus::Ready,
            createdAt: $today->subMinutes(6),
            acceptedAt: $today->subMinutes(5),
            preparingAt: $today->subMinutes(4),
            readyAt: $today,
            placedAt: $today->subMinutes(6),
            productName: 'Boundary Latte',
        );

        $service = app(OperationalPerformanceReportingServiceInterface::class);
        $yesterdayReport = $service->buildAdminReport([
            'preset' => OperationalPerformanceReportingService::PRESET_CUSTOM,
            'from' => CarbonImmutable::now($timezone)->subDay()->format('Y-m-d'),
            'to' => CarbonImmutable::now($timezone)->subDay()->format('Y-m-d'),
        ]);
        $this->assertSame(1, $yesterdayReport['stations'][PreparationStation::Bar->value]['ready_tickets']);
        $this->assertSame($timezone, $yesterdayReport['timezone']);

        $manager = User::factory()->manager()->create();
        $export = $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.operational-performance.export.preparations', [
                'preset' => 'today',
            ]));
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('queue_wait_seconds', $csv);
        $this->assertStringContainsString('Boundary Latte', $csv);
        $this->assertStringContainsString((string) $todayTicket->order->order_number, $csv);

        $metrics = app(OperationalPerformanceReportingService::class)->ticketMetrics($todayTicket->fresh());
        $this->assertSame(60, $metrics['queue_wait_seconds']);
        $this->assertSame(240, $metrics['prep_seconds']);
    }

    protected function makeTicket(
        PreparationStation $station,
        OrderPreparationStatus $status,
        $createdAt,
        $acceptedAt = null,
        $preparingAt = null,
        $readyAt = null,
        $placedAt = null,
        string $productName = 'Item',
    ): OrderPreparation {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $productName,
            'product_type' => ProductType::Beverage,
            'preparation_station' => $station,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Regular']);

        $order = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'status' => $status === OrderPreparationStatus::Cancelled
                ? OrderStatus::Cancelled
                : ($readyAt ? OrderStatus::ReadyForPickup : OrderStatus::Preparing),
            'placed_at' => $placedAt ?? $createdAt,
            'accepted_at' => $acceptedAt,
            'ready_for_pickup_at' => $readyAt,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'preparation_station' => $station,
            'product_name' => $productName,
            'variant_name' => $variant->name,
            'quantity' => 1,
            'unit_price' => '50.00',
            'line_subtotal' => '50.00',
        ]);

        return $this->attachTicket($order, $station, $status, [
            'created_at' => $createdAt,
            'accepted_at' => $acceptedAt,
            'preparing_at' => $preparingAt,
            'ready_at' => $readyAt,
            'completed_at' => $readyAt,
            'cancelled_at' => $status === OrderPreparationStatus::Cancelled ? ($acceptedAt ?? $createdAt) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $timestamps
     */
    protected function attachTicket(
        Order $order,
        PreparationStation $station,
        OrderPreparationStatus $status,
        array $timestamps,
    ): OrderPreparation {
        $ticket = OrderPreparation::query()->create([
            'order_id' => $order->id,
            'station' => $station,
            'status' => $status,
            'accepted_at' => $timestamps['accepted_at'] ?? null,
            'preparing_at' => $timestamps['preparing_at'] ?? null,
            'ready_at' => $timestamps['ready_at'] ?? null,
            'completed_at' => $timestamps['completed_at'] ?? ($timestamps['ready_at'] ?? null),
            'cancelled_at' => $timestamps['cancelled_at'] ?? null,
        ]);

        OrderPreparation::query()->whereKey($ticket->id)->update([
            'created_at' => $timestamps['created_at'],
            'updated_at' => $timestamps['created_at'],
        ]);

        return $ticket->fresh(['order.items.product.category']);
    }

    protected function makeDiningSession(
        DiningSessionStatus $status,
        $openedAt,
        $billRequestedAt = null,
        $paidAt = null,
        $closedAt = null,
    ): DiningSession {
        $table = CafeTable::factory()->create();

        return DiningSession::query()->create([
            'session_number' => 'DS-'.now()->format('ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'cafe_table_id' => $table->id,
            'opened_by_user_id' => User::factory()->create(['role' => UserRole::Waiter])->id,
            'status' => $status,
            'guest_count' => 2,
            'table_name_snapshot' => $table->snapshotLabel(),
            'opened_at' => $openedAt,
            'billing_requested_at' => $billRequestedAt,
            'bill_generated_at' => $billRequestedAt,
            'paid_at' => $paidAt,
            'closed_at' => $closedAt,
            'payment_method' => $paidAt ? PaymentMethod::Cash : null,
            'payment_status' => $paidAt ? PaymentStatus::Confirmed : PaymentStatus::Pending,
            'subtotal_amount' => $paidAt ? '200.00' : '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'taxable_amount' => $paidAt ? '200.00' : '0.00',
            'tax_enabled_snapshot' => false,
            'tax_percent_snapshot' => '0.00',
            'tax_inclusive_snapshot' => false,
            'total_amount' => $paidAt ? '200.00' : '0.00',
        ]);
    }

    protected function makeDiningRound(DiningSession $session, int $round, $placedAt): Order
    {
        return Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
            'dining_session_id' => $session->id,
            'dining_round_number' => $round,
            'status' => OrderStatus::ReadyForPickup,
            'payment_status' => PaymentStatus::Pending,
            'placed_at' => $placedAt,
            'accepted_at' => $placedAt,
            'ready_for_pickup_at' => $placedAt,
        ]);
    }
}
