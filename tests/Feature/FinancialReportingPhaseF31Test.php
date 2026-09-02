<?php

namespace Tests\Feature;

use App\Enums\CustomerRewardType;
use App\Enums\DiningSessionStatus;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PromotionDiscountType;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderPromotion;
use App\Models\OrderRewardRedemption;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\Reporting\FinancialReportingService;
use App\Services\Reporting\FinancialReportingServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportingPhaseF31Test extends TestCase
{
    use RefreshDatabase;

    public function test_paid_takeaway_counted_and_unpaid_cancelled_rejected_excluded(): void
    {
        $paid = $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Takeaway,
            total: '100.00',
            subtotal: '100.00',
            tax: '0.00',
            discount: '0.00',
        );
        $this->makeRetailOrder([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_status' => PaymentStatus::Pending,
            'status' => OrderStatus::PendingPayment,
            'total_amount' => '50.00',
            'subtotal' => '50.00',
            'payment_confirmed_at' => null,
            'placed_at' => now(),
        ]);
        $this->makeRetailOrder([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Cancelled,
            'total_amount' => '75.00',
            'subtotal' => '75.00',
            'payment_confirmed_at' => now(),
            'placed_at' => now(),
            'cancelled_at' => now(),
        ]);
        $this->makeRetailOrder([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Rejected,
            'total_amount' => '25.00',
            'subtotal' => '25.00',
            'payment_confirmed_at' => now(),
            'placed_at' => now(),
            'rejected_at' => now(),
        ]);

        $report = app(FinancialReportingServiceInterface::class)->buildAdminReport([
            'preset' => FinancialReportingService::PRESET_TODAY,
        ]);

        $this->assertSame('100.00', $report['summary']['net_final_collected']);
        $this->assertSame(1, $report['summary']['transaction_count']);
        $this->assertSame(1, $report['summary']['retail_order_count']);
        $this->assertSame($paid->order_number, $report['transactions'][0]['reference']);
        $this->assertSame(1, $report['cancellations']['cancelled']['count']);
        $this->assertSame(1, $report['cancellations']['rejected']['count']);
        $this->assertSame(2, $report['cancellations']['paid_cancellation_exceptions']['count']);
    }

    public function test_paid_delivery_and_dining_session_counted_once_not_rounds(): void
    {
        $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Delivery,
            total: '200.00',
            subtotal: '200.00',
            tax: '0.00',
            discount: '0.00',
        );

        $session = $this->makePaidDiningSession(
            total: '300.00',
            subtotal: '300.00',
            tax: '0.00',
            discount: '0.00',
        );

        // Dining rounds must never contribute revenue.
        Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
            'dining_session_id' => $session->id,
            'dining_round_number' => 1,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Completed,
            'total_amount' => '150.00',
            'subtotal' => '150.00',
            'payment_confirmed_at' => now(),
        ]);
        Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
            'dining_session_id' => $session->id,
            'dining_round_number' => 2,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Completed,
            'total_amount' => '150.00',
            'subtotal' => '150.00',
            'payment_confirmed_at' => now(),
        ]);

        $report = app(FinancialReportingServiceInterface::class)->buildAdminReport([
            'preset' => FinancialReportingService::PRESET_TODAY,
        ]);

        $this->assertSame('500.00', $report['summary']['net_final_collected']);
        $this->assertSame(2, $report['summary']['transaction_count']);
        $this->assertSame(1, $report['summary']['retail_order_count']);
        $this->assertSame(1, $report['summary']['dining_session_count']);
        $this->assertSame(2, $report['channels']['dining']['round_count']);
        $this->assertSame(1, $report['channels']['delivery']['transactions']);
    }

    public function test_cash_and_upi_pending_excluded_confirmed_counted(): void
    {
        $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Takeaway,
            total: '80.00',
            subtotal: '80.00',
            tax: '0.00',
            discount: '0.00',
            paymentMethod: PaymentMethod::Cash,
        );
        $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Takeaway,
            total: '90.00',
            subtotal: '90.00',
            tax: '0.00',
            discount: '0.00',
            paymentMethod: PaymentMethod::Manual,
        );
        $this->makeRetailOrder([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_method' => PaymentMethod::Cash,
            'payment_status' => PaymentStatus::Pending,
            'status' => OrderStatus::Accepted,
            'total_amount' => '40.00',
            'subtotal' => '40.00',
            'payment_confirmed_at' => null,
            'placed_at' => now(),
        ]);
        $this->makeRetailOrder([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_method' => PaymentMethod::Manual,
            'payment_status' => PaymentStatus::AwaitingReview,
            'status' => OrderStatus::PendingPayment,
            'total_amount' => '55.00',
            'subtotal' => '55.00',
            'payment_confirmed_at' => null,
            'placed_at' => now(),
        ]);

        $report = app(FinancialReportingServiceInterface::class)->buildAdminReport([
            'preset' => FinancialReportingService::PRESET_TODAY,
        ]);

        $this->assertSame('170.00', $report['summary']['net_final_collected']);
        $this->assertSame('80.00', $report['payments']['cash_collected']);
        $this->assertSame('90.00', $report['payments']['upi_confirmed']);
        $this->assertSame(2, $report['payments']['pending_payment_count']);
    }

    public function test_gst_uses_snapshots_not_current_settings(): void
    {
        $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Takeaway,
            total: '110.00',
            subtotal: '100.00',
            tax: '10.00',
            discount: '0.00',
            taxable: '100.00',
            taxPercent: '10.00',
            taxEnabled: true,
            taxInclusive: false,
        );

        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::TaxPercent->value],
            [
                'section' => WebsiteSettingKey::TaxPercent->section(),
                'value_type' => WebsiteSettingKey::TaxPercent->valueType(),
                'value' => '99.00',
            ],
        );

        $report = app(FinancialReportingServiceInterface::class)->buildAdminReport([
            'preset' => FinancialReportingService::PRESET_TODAY,
        ]);

        $this->assertSame('10.00', $report['gst']['gst_amount']);
        $this->assertSame('100.00', $report['gst']['taxable_base']);
        $this->assertSame(1, $report['gst']['exclusive_transaction_count']);
    }

    public function test_discounts_promotion_referral_and_free_drink(): void
    {
        $order = $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Takeaway,
            total: '70.00',
            subtotal: '100.00',
            tax: '0.00',
            discount: '30.00',
        );

        OrderPromotion::query()->create([
            'order_id' => $order->id,
            'promotion_id' => null,
            'name_snapshot' => 'Promo',
            'code_snapshot' => null,
            'discount_type_snapshot' => PromotionDiscountType::Fixed,
            'discount_value_snapshot' => '10.00',
            'discount_amount' => '10.00',
            'sort_order' => 1,
        ]);

        OrderRewardRedemption::query()->create([
            'order_id' => $order->id,
            'customer_reward_id' => null,
            'reward_type' => CustomerRewardType::Coupon,
            'description_snapshot' => 'Referral coupon',
            'benefit_amount' => '5.00',
            'original_amount' => '5.00',
            'preserved_taxable_amount' => '5.00',
            'quantity' => 1,
            'coupon_code_snapshot' => 'REF5',
        ]);

        OrderRewardRedemption::query()->create([
            'order_id' => $order->id,
            'customer_reward_id' => null,
            'reward_type' => CustomerRewardType::FreeDrink,
            'description_snapshot' => 'Free drink reward',
            'benefit_amount' => '15.00',
            'original_amount' => '15.00',
            'preserved_taxable_amount' => '15.00',
            'quantity' => 1,
            'product_name_snapshot' => 'Latte',
        ]);

        $report = app(FinancialReportingServiceInterface::class)->buildAdminReport([
            'preset' => FinancialReportingService::PRESET_TODAY,
        ]);

        $this->assertSame('10.00', $report['discounts']['promotion_discounts']);
        $this->assertSame('5.00', $report['discounts']['referral_coupon_discounts']);
        $this->assertSame('15.00', $report['discounts']['free_drink_benefit_value']);
        $this->assertSame('30.00', $report['discounts']['total_discounts']);
    }

    public function test_custom_range_uses_business_timezone_boundaries(): void
    {
        $timezone = app(CafeAvailabilityServiceInterface::class)->timezone();
        $localYesterdayMorning = CarbonImmutable::now($timezone)->subDay()->setTime(10, 0);
        $localTodayMorning = CarbonImmutable::now($timezone)->setTime(10, 0);

        $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Takeaway,
            total: '40.00',
            subtotal: '40.00',
            tax: '0.00',
            discount: '0.00',
            confirmedAt: $localYesterdayMorning->setTimezone('UTC'),
        );
        $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Takeaway,
            total: '60.00',
            subtotal: '60.00',
            tax: '0.00',
            discount: '0.00',
            confirmedAt: $localTodayMorning->setTimezone('UTC'),
        );

        $report = app(FinancialReportingServiceInterface::class)->buildAdminReport([
            'preset' => FinancialReportingService::PRESET_CUSTOM,
            'from' => $localYesterdayMorning->format('Y-m-d'),
            'to' => $localYesterdayMorning->format('Y-m-d'),
        ]);

        $this->assertSame('40.00', $report['summary']['net_final_collected']);
        $this->assertSame(1, $report['summary']['transaction_count']);
        $this->assertSame($timezone, $report['timezone']);
    }

    public function test_admin_can_view_and_export_financial_report(): void
    {
        $manager = User::factory()->manager()->create();
        $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Takeaway,
            total: '120.00',
            subtotal: '100.00',
            tax: '20.00',
            discount: '0.00',
            taxable: '100.00',
        );

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.financial.index', ['preset' => 'today']))
            ->assertOk()
            ->assertSee('Financial Report')
            ->assertSee('120.00');

        $export = $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.financial.export', ['preset' => 'today']));

        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        $csv = $export->streamedContent();
        $this->assertStringContainsString('transaction_reference', $csv);
        $this->assertStringContainsString('120.00', $csv);
        $this->assertStringContainsString('takeaway', $csv);
    }

    public function test_operator_sees_reconciliation_not_financial_report(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator, 'admin')
            ->get(route('operator.reconciliation.index'))
            ->assertOk()
            ->assertSee('Today Reconciliation')
            ->assertDontSee('Gross Paid Sales');

        $this->actingAs($operator, 'admin')
            ->get(route('administrator.reports.financial.index'))
            ->assertForbidden();
    }

    public function test_barista_chef_waiter_forbidden_from_financial_reporting(): void
    {
        foreach ([
            User::factory()->barista()->create(),
            User::factory()->chef()->create(),
            User::factory()->waiter()->create(),
        ] as $user) {
            $this->actingAs($user, 'admin')
                ->get(route('administrator.reports.financial.index'))
                ->assertForbidden();
        }
    }

    public function test_csv_totals_match_service_aggregates(): void
    {
        $this->makePaidRetailOrder(
            fulfilment: OrderFulfilmentMethod::Takeaway,
            total: '110.00',
            subtotal: '100.00',
            tax: '10.00',
            discount: '0.00',
        );
        $this->makePaidDiningSession(
            total: '220.00',
            subtotal: '200.00',
            tax: '20.00',
            discount: '0.00',
            method: PaymentMethod::Cash,
        );

        $service = app(FinancialReportingServiceInterface::class);
        $report = $service->buildAdminReport(['preset' => FinancialReportingService::PRESET_TODAY]);

        $this->assertSame('330.00', $report['summary']['net_final_collected']);
        $this->assertSame('30.00', $report['summary']['gst_collected']);

        $manager = User::factory()->manager()->create();
        $export = $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.financial.export', ['preset' => 'today']));

        $export->assertOk();
        $csv = $export->streamedContent();

        $this->assertStringContainsString('110.00', $csv);
        $this->assertStringContainsString('220.00', $csv);
        $this->assertStringContainsString(',dining,', $csv);
    }

    protected function makePaidRetailOrder(
        OrderFulfilmentMethod $fulfilment,
        string $total,
        string $subtotal,
        string $tax,
        string $discount,
        PaymentMethod $paymentMethod = PaymentMethod::Manual,
        ?CarbonImmutable $confirmedAt = null,
        string $taxable = '0.00',
        string $taxPercent = '0.00',
        bool $taxEnabled = false,
        bool $taxInclusive = false,
    ): Order {
        $confirmedAt ??= CarbonImmutable::now('UTC');

        return $this->makeRetailOrder([
            'fulfilment_method' => $fulfilment,
            'payment_method' => $paymentMethod,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Completed,
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_amount' => $tax,
            'taxable_amount' => $taxable === '0.00' ? $subtotal : $taxable,
            'tax_enabled_snapshot' => $taxEnabled,
            'tax_percent_snapshot' => $taxPercent,
            'tax_inclusive_snapshot' => $taxInclusive,
            'tax_label_snapshot' => $taxEnabled ? 'GST' : null,
            'total_amount' => $total,
            'payment_confirmed_at' => $confirmedAt,
            'placed_at' => $confirmedAt,
            'dining_session_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeRetailOrder(array $overrides): Order
    {
        return Order::factory()->create(array_merge([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'dining_session_id' => null,
            'dining_round_number' => null,
        ], $overrides));
    }

    protected function makePaidDiningSession(
        string $total,
        string $subtotal,
        string $tax,
        string $discount,
        PaymentMethod $method = PaymentMethod::Manual,
        ?CarbonImmutable $paidAt = null,
    ): DiningSession {
        $paidAt ??= CarbonImmutable::now('UTC');
        $table = CafeTable::factory()->create();

        return DiningSession::query()->create([
            'session_number' => 'DS-'.now()->format('ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'cafe_table_id' => $table->id,
            'opened_by_user_id' => User::factory()->create(['role' => UserRole::Waiter])->id,
            'status' => DiningSessionStatus::Closed,
            'guest_count' => 2,
            'table_name_snapshot' => $table->snapshotLabel(),
            'opened_at' => $paidAt->subHour(),
            'billing_requested_at' => $paidAt->subMinutes(30),
            'bill_generated_at' => $paidAt->subMinutes(20),
            'paid_at' => $paidAt,
            'closed_at' => $paidAt,
            'payment_method' => $method,
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'taxable_amount' => $subtotal,
            'tax_enabled_snapshot' => false,
            'tax_percent_snapshot' => '0.00',
            'tax_inclusive_snapshot' => false,
            'total_amount' => $total,
        ]);
    }
}
