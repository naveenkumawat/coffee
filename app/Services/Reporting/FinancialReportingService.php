<?php

namespace App\Services\Reporting;

use App\Enums\CustomerRewardType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DiningSession;
use App\Models\DiningSessionPromotion;
use App\Models\Order;
use App\Models\OrderPromotion;
use App\Models\OrderRewardRedemption;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportingService implements FinancialReportingServiceInterface
{
    public const PRESET_TODAY = 'today';

    public const PRESET_YESTERDAY = 'yesterday';

    public const PRESET_LAST_7_DAYS = 'last_7_days';

    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_CUSTOM = 'custom';

    public const CHANNEL_ALL = 'all';

    public const CHANNEL_TAKEAWAY = 'takeaway';

    public const CHANNEL_DELIVERY = 'delivery';

    public const CHANNEL_DINING = 'dining';

    public const PAYMENT_ALL = 'all';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_UPI = 'manual';

    public function __construct(
        protected CafeAvailabilityServiceInterface $cafeAvailability,
    ) {}

    public function buildAdminReport(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters);
        $channel = $this->normalizeChannel($filters['channel'] ?? null);
        $paymentMethod = $this->normalizePaymentMethod($filters['payment_method'] ?? null);

        $retail = $this->aggregateRetail($period['start_utc'], $period['end_utc'], $channel, $paymentMethod);
        $dining = $this->aggregateDining($period['start_utc'], $period['end_utc'], $channel, $paymentMethod);

        $gross = $this->addMoney($retail['gross'], $dining['gross']);
        $discounts = $this->addMoney($retail['discounts'], $dining['discounts']);
        $tax = $this->addMoney($retail['tax'], $dining['tax']);
        $taxable = $this->addMoney($retail['taxable'], $dining['taxable']);
        $net = $this->addMoney($retail['net'], $dining['net']);
        $txnCount = $retail['count'] + $dining['count'];

        return [
            'timezone' => $period['timezone'],
            'preset' => $period['preset'],
            'start_local' => $period['start_local'],
            'end_local' => $period['end_local'],
            'start_utc' => $period['start_utc'],
            'end_utc' => $period['end_utc'],
            'channel' => $channel,
            'payment_method' => $paymentMethod,
            'summary' => [
                'gross_paid_sales' => $gross,
                'discounts' => $discounts,
                'gst_collected' => $tax,
                'taxable_base' => $taxable,
                'net_final_collected' => $net,
                'transaction_count' => $txnCount,
                'average_transaction_value' => $txnCount > 0
                    ? $this->divideMoney($net, (string) $txnCount)
                    : '0.00',
                'retail_order_count' => $retail['count'],
                'dining_session_count' => $dining['count'],
            ],
            'channels' => [
                'takeaway' => $this->retailChannelMetrics(
                    $this->aggregateRetail($period['start_utc'], $period['end_utc'], self::CHANNEL_TAKEAWAY, $paymentMethod),
                ),
                'delivery' => $this->retailChannelMetrics(
                    $this->aggregateRetail($period['start_utc'], $period['end_utc'], self::CHANNEL_DELIVERY, $paymentMethod),
                ),
                'dining' => $this->diningChannelMetrics(
                    $this->aggregateDining($period['start_utc'], $period['end_utc'], self::CHANNEL_DINING, $paymentMethod),
                    $period['start_utc'],
                    $period['end_utc'],
                    $paymentMethod,
                ),
            ],
            'payments' => $this->paymentReconciliation($period['start_utc'], $period['end_utc'], $channel),
            'gst' => $this->gstReport($period['start_utc'], $period['end_utc'], $channel, $paymentMethod, $taxable, $tax),
            'discounts' => $this->discountReport($period['start_utc'], $period['end_utc'], $channel, $paymentMethod),
            'cancellations' => $this->cancellationReport($period['start_utc'], $period['end_utc'], $channel, $paymentMethod),
            'transactions' => $this->transactionRows($period['start_utc'], $period['end_utc'], $channel, $paymentMethod, 100),
        ];
    }

    public function buildOperatorReconciliation(): array
    {
        $period = $this->resolvePeriod(['preset' => self::PRESET_TODAY]);
        $start = $period['start_utc'];
        $end = $period['end_utc'];

        $paidRetail = $this->retailRevenueQuery($start, $end, self::CHANNEL_ALL, self::PAYMENT_ALL)->count();
        $paidDining = $this->diningRevenueQuery($start, $end, self::CHANNEL_ALL, self::PAYMENT_ALL)->count();

        return [
            'timezone' => $period['timezone'],
            'start_local' => $period['start_local'],
            'end_local' => $period['end_local'],
            'paid_transactions_today' => $paidRetail + $paidDining,
            'paid_retail_orders_today' => $paidRetail,
            'paid_dining_sessions_today' => $paidDining,
            'cash_pending' => Order::query()
                ->whereNull('dining_session_id')
                ->where('payment_method', PaymentMethod::Cash)
                ->where('payment_status', PaymentStatus::Pending)
                ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
                ->count()
                + DiningSession::query()
                    ->where('payment_method', PaymentMethod::Cash)
                    ->where('payment_status', PaymentStatus::Pending)
                    ->count(),
            'cash_received' => $this->sumConfirmedByMethod($start, $end, PaymentMethod::Cash)['count'],
            'upi_awaiting_review' => Order::query()
                ->whereNull('dining_session_id')
                ->where('payment_method', PaymentMethod::Manual)
                ->where('payment_status', PaymentStatus::AwaitingReview)
                ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
                ->count()
                + DiningSession::query()
                    ->where('payment_method', PaymentMethod::Manual)
                    ->where('payment_status', PaymentStatus::AwaitingReview)
                    ->count(),
            'upi_confirmed_today' => $this->sumConfirmedByMethod($start, $end, PaymentMethod::Manual)['count'],
            'channel_counts' => [
                'takeaway' => $this->retailRevenueQuery($start, $end, self::CHANNEL_TAKEAWAY, self::PAYMENT_ALL)->count(),
                'delivery' => $this->retailRevenueQuery($start, $end, self::CHANNEL_DELIVERY, self::PAYMENT_ALL)->count(),
                'dining' => $paidDining,
            ],
            'orders_needing_action' => Order::query()
                ->whereNull('dining_session_id')
                ->where(function (Builder $query): void {
                    $query->where(function (Builder $inner): void {
                        $inner->where('status', OrderStatus::PaymentConfirmed)
                            ->where('payment_status', PaymentStatus::Confirmed);
                    })->orWhere(function (Builder $inner): void {
                        $inner->where('payment_method', PaymentMethod::Manual)
                            ->where('payment_status', PaymentStatus::AwaitingReview)
                            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected]);
                    })->orWhere(function (Builder $inner): void {
                        $inner->where('payment_method', PaymentMethod::Cash)
                            ->where('payment_status', PaymentStatus::Pending)
                            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected]);
                    });
                })
                ->count(),
            'dining_needing_action' => DiningSession::query()
                ->where(function (Builder $query): void {
                    $query->where('payment_status', PaymentStatus::AwaitingReview)
                        ->orWhere(function (Builder $inner): void {
                            $inner->where('payment_method', PaymentMethod::Cash)
                                ->where('payment_status', PaymentStatus::Pending)
                                ->whereNotNull('bill_generated_at');
                        });
                })
                ->count(),
        ];
    }

    public function exportAdminCsv(array $filters = []): StreamedResponse
    {
        $report = $this->buildAdminReport($filters);
        $rows = $this->transactionRows(
            $report['start_utc'],
            $report['end_utc'],
            $report['channel'],
            $report['payment_method'],
            null,
        );

        $filename = sprintf(
            'financial-report-%s-to-%s.csv',
            $report['start_local']->format('Ymd'),
            $report['end_local']->format('Ymd'),
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'date_time',
                'transaction_reference',
                'channel',
                'payment_method',
                'subtotal',
                'discount',
                'gst',
                'final_total',
                'payment_status',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['date_time'],
                    $row['reference'],
                    $row['channel'],
                    $row['payment_method'],
                    $row['subtotal'],
                    $row['discount'],
                    $row['gst'],
                    $row['final_total'],
                    $row['payment_status'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{preset?: string|null, from?: string|null, to?: string|null}  $filters
     * @return array{
     *     timezone: string,
     *     preset: string,
     *     start_local: CarbonImmutable,
     *     end_local: CarbonImmutable,
     *     start_utc: CarbonImmutable,
     *     end_utc: CarbonImmutable
     * }
     */
    public function resolvePeriod(array $filters): array
    {
        $timezone = $this->cafeAvailability->timezone();
        $now = CarbonImmutable::now($timezone);
        $preset = (string) ($filters['preset'] ?? self::PRESET_TODAY);

        if (! in_array($preset, [
            self::PRESET_TODAY,
            self::PRESET_YESTERDAY,
            self::PRESET_LAST_7_DAYS,
            self::PRESET_THIS_MONTH,
            self::PRESET_CUSTOM,
        ], true)) {
            $preset = self::PRESET_TODAY;
        }

        if ($preset === self::PRESET_CUSTOM) {
            $from = trim((string) ($filters['from'] ?? ''));
            $to = trim((string) ($filters['to'] ?? ''));

            if ($from === '' || $to === '') {
                throw ValidationException::withMessages([
                    'from' => 'Custom range requires both from and to dates.',
                ]);
            }

            try {
                $startLocal = CarbonImmutable::createFromFormat('Y-m-d', $from, $timezone)->startOfDay();
                $endLocal = CarbonImmutable::createFromFormat('Y-m-d', $to, $timezone)->endOfDay();
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'from' => 'Custom dates must use Y-m-d format.',
                ]);
            }

            if ($endLocal->lt($startLocal)) {
                throw ValidationException::withMessages([
                    'to' => 'The end date must be on or after the start date.',
                ]);
            }
        } else {
            [$startLocal, $endLocal] = match ($preset) {
                self::PRESET_YESTERDAY => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
                self::PRESET_LAST_7_DAYS => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
                self::PRESET_THIS_MONTH => [$now->startOfMonth()->startOfDay(), $now->endOfDay()],
                default => [$now->startOfDay(), $now->endOfDay()],
            };
        }

        return [
            'timezone' => $timezone,
            'preset' => $preset,
            'start_local' => $startLocal,
            'end_local' => $endLocal,
            'start_utc' => $startLocal->setTimezone('UTC'),
            'end_utc' => $endLocal->setTimezone('UTC'),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function utcRange(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        return [
            $startUtc->utc()->format('Y-m-d H:i:s'),
            $endUtc->utc()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{gross: string, discounts: string, tax: string, taxable: string, net: string, count: int}
     */
    protected function aggregateRetail(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $channel,
        string $paymentMethod,
    ): array {
        if ($channel === self::CHANNEL_DINING) {
            return $this->emptyAggregate();
        }

        $row = $this->retailRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)
            ->selectRaw('COUNT(*) as txn_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sum')
            ->selectRaw('COALESCE(SUM(discount_total), 0) as discount_sum')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax_sum')
            ->selectRaw('COALESCE(SUM(taxable_amount), 0) as taxable_sum')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as net_sum')
            ->first();

        return [
            'gross' => $this->asMoney($row?->gross_sum),
            'discounts' => $this->asMoney($row?->discount_sum),
            'tax' => $this->asMoney($row?->tax_sum),
            'taxable' => $this->asMoney($row?->taxable_sum),
            'net' => $this->asMoney($row?->net_sum),
            'count' => (int) ($row?->txn_count ?? 0),
        ];
    }

    /**
     * @return array{gross: string, discounts: string, tax: string, taxable: string, net: string, count: int}
     */
    protected function aggregateDining(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $channel,
        string $paymentMethod,
    ): array {
        if (in_array($channel, [self::CHANNEL_TAKEAWAY, self::CHANNEL_DELIVERY], true)) {
            return $this->emptyAggregate();
        }

        $row = $this->diningRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)
            ->selectRaw('COUNT(*) as txn_count')
            ->selectRaw('COALESCE(SUM(subtotal_amount), 0) as gross_sum')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_sum')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax_sum')
            ->selectRaw('COALESCE(SUM(taxable_amount), 0) as taxable_sum')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as net_sum')
            ->first();

        return [
            'gross' => $this->asMoney($row?->gross_sum),
            'discounts' => $this->asMoney($row?->discount_sum),
            'tax' => $this->asMoney($row?->tax_sum),
            'taxable' => $this->asMoney($row?->taxable_sum),
            'net' => $this->asMoney($row?->net_sum),
            'count' => (int) ($row?->txn_count ?? 0),
        ];
    }

    /**
     * @param  array{gross: string, discounts: string, tax: string, taxable: string, net: string, count: int}  $agg
     * @return array<string, mixed>
     */
    protected function retailChannelMetrics(array $agg): array
    {
        return [
            'transactions' => $agg['count'],
            'paid_sales' => $agg['net'],
            'gross_sales' => $agg['gross'],
            'average_value' => $agg['count'] > 0
                ? $this->divideMoney($agg['net'], (string) $agg['count'])
                : '0.00',
        ];
    }

    /**
     * @param  array{gross: string, discounts: string, tax: string, taxable: string, net: string, count: int}  $agg
     * @return array<string, mixed>
     */
    protected function diningChannelMetrics(
        array $agg,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $paymentMethod,
    ): array {
        $roundCount = 0;

        if ($agg['count'] > 0) {
            $sessionIds = $this->diningRevenueQuery($startUtc, $endUtc, self::CHANNEL_DINING, $paymentMethod)->pluck('id');
            $roundCount = Order::query()->whereIn('dining_session_id', $sessionIds)->count();
        }

        return [
            'paid_sessions' => $agg['count'],
            'sales' => $agg['net'],
            'gross_sales' => $agg['gross'],
            'average_session_value' => $agg['count'] > 0
                ? $this->divideMoney($agg['net'], (string) $agg['count'])
                : '0.00',
            'round_count' => $roundCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentReconciliation(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $channel,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $takeawayCash = $channel === self::CHANNEL_DINING || $channel === self::CHANNEL_DELIVERY
            ? ['amount' => '0.00', 'count' => 0]
            : $this->sumRetailConfirmed($startUtc, $endUtc, OrderFulfilmentMethod::Takeaway, PaymentMethod::Cash);
        $deliveryCash = $channel === self::CHANNEL_DINING || $channel === self::CHANNEL_TAKEAWAY
            ? ['amount' => '0.00', 'count' => 0]
            : $this->sumRetailConfirmed($startUtc, $endUtc, OrderFulfilmentMethod::Delivery, PaymentMethod::Cash);
        $takeawayUpi = $channel === self::CHANNEL_DINING || $channel === self::CHANNEL_DELIVERY
            ? ['amount' => '0.00', 'count' => 0]
            : $this->sumRetailConfirmed($startUtc, $endUtc, OrderFulfilmentMethod::Takeaway, PaymentMethod::Manual);
        $deliveryUpi = $channel === self::CHANNEL_DINING || $channel === self::CHANNEL_TAKEAWAY
            ? ['amount' => '0.00', 'count' => 0]
            : $this->sumRetailConfirmed($startUtc, $endUtc, OrderFulfilmentMethod::Delivery, PaymentMethod::Manual);
        $diningCash = in_array($channel, [self::CHANNEL_TAKEAWAY, self::CHANNEL_DELIVERY], true)
            ? ['amount' => '0.00', 'count' => 0]
            : $this->sumDiningConfirmed($startUtc, $endUtc, PaymentMethod::Cash);
        $diningUpi = in_array($channel, [self::CHANNEL_TAKEAWAY, self::CHANNEL_DELIVERY], true)
            ? ['amount' => '0.00', 'count' => 0]
            : $this->sumDiningConfirmed($startUtc, $endUtc, PaymentMethod::Manual);

        $pendingAmount = '0.00';
        $pendingCount = 0;
        $rejectedAmount = '0.00';
        $rejectedCount = 0;

        if ($channel !== self::CHANNEL_DINING) {
            $pendingRetail = Order::query()
                ->whereNull('dining_session_id')
                ->when($channel === self::CHANNEL_TAKEAWAY, fn (Builder $q) => $q->where('fulfilment_method', OrderFulfilmentMethod::Takeaway))
                ->when($channel === self::CHANNEL_DELIVERY, fn (Builder $q) => $q->where('fulfilment_method', OrderFulfilmentMethod::Delivery))
                ->when($channel === self::CHANNEL_ALL, fn (Builder $q) => $q->whereIn('fulfilment_method', [
                    OrderFulfilmentMethod::Takeaway,
                    OrderFulfilmentMethod::Delivery,
                ]))
                ->whereIn('payment_status', [PaymentStatus::Pending, PaymentStatus::AwaitingReview])
                ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
                ->whereBetween('placed_at', [$from, $to])
                ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as amount_sum')
                ->first();

            $pendingAmount = $this->addMoney($pendingAmount, $this->asMoney($pendingRetail?->amount_sum));
            $pendingCount += (int) ($pendingRetail?->txn_count ?? 0);

            $rejectedRetail = Order::query()
                ->whereNull('dining_session_id')
                ->when($channel === self::CHANNEL_TAKEAWAY, fn (Builder $q) => $q->where('fulfilment_method', OrderFulfilmentMethod::Takeaway))
                ->when($channel === self::CHANNEL_DELIVERY, fn (Builder $q) => $q->where('fulfilment_method', OrderFulfilmentMethod::Delivery))
                ->when($channel === self::CHANNEL_ALL, fn (Builder $q) => $q->whereIn('fulfilment_method', [
                    OrderFulfilmentMethod::Takeaway,
                    OrderFulfilmentMethod::Delivery,
                ]))
                ->where('payment_status', PaymentStatus::Rejected)
                ->whereBetween('placed_at', [$from, $to])
                ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as amount_sum')
                ->first();

            $rejectedAmount = $this->addMoney($rejectedAmount, $this->asMoney($rejectedRetail?->amount_sum));
            $rejectedCount += (int) ($rejectedRetail?->txn_count ?? 0);
        }

        if (! in_array($channel, [self::CHANNEL_TAKEAWAY, self::CHANNEL_DELIVERY], true)) {
            $pendingDining = DiningSession::query()
                ->whereIn('payment_status', [PaymentStatus::Pending, PaymentStatus::AwaitingReview])
                ->whereBetween('opened_at', [$from, $to])
                ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(COALESCE(total_amount, 0)), 0) as amount_sum')
                ->first();
            $pendingAmount = $this->addMoney($pendingAmount, $this->asMoney($pendingDining?->amount_sum));
            $pendingCount += (int) ($pendingDining?->txn_count ?? 0);

            $rejectedDining = DiningSession::query()
                ->where('payment_status', PaymentStatus::Rejected)
                ->whereBetween('opened_at', [$from, $to])
                ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(COALESCE(total_amount, 0)), 0) as amount_sum')
                ->first();
            $rejectedAmount = $this->addMoney($rejectedAmount, $this->asMoney($rejectedDining?->amount_sum));
            $rejectedCount += (int) ($rejectedDining?->txn_count ?? 0);
        }

        return [
            'cash_collected' => $this->addMoney(
                $this->addMoney($takeawayCash['amount'], $deliveryCash['amount']),
                $diningCash['amount'],
            ),
            'upi_confirmed' => $this->addMoney(
                $this->addMoney($takeawayUpi['amount'], $deliveryUpi['amount']),
                $diningUpi['amount'],
            ),
            'pending_payment' => $pendingAmount,
            'pending_payment_count' => $pendingCount,
            'rejected_failed_proof' => $rejectedAmount,
            'rejected_failed_proof_count' => $rejectedCount,
            'takeaway_cash' => $takeawayCash,
            'delivery_cash' => $deliveryCash,
            'dining_cash' => $diningCash,
            'takeaway_upi' => $takeawayUpi,
            'delivery_upi' => $deliveryUpi,
            'dining_upi' => $diningUpi,
            'retail_upi' => [
                'amount' => $this->addMoney($takeawayUpi['amount'], $deliveryUpi['amount']),
                'count' => $takeawayUpi['count'] + $deliveryUpi['count'],
            ],
        ];
    }

    /**
     * @return array{amount: string, count: int}
     */
    protected function sumRetailConfirmed(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        OrderFulfilmentMethod $fulfilment,
        PaymentMethod $method,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $row = Order::query()
            ->whereNull('dining_session_id')
            ->where('fulfilment_method', $fulfilment)
            ->where('payment_method', $method)
            ->where('payment_status', PaymentStatus::Confirmed)
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
            ->whereBetween('payment_confirmed_at', [$from, $to])
            ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as amount_sum')
            ->first();

        return [
            'amount' => $this->asMoney($row?->amount_sum),
            'count' => (int) ($row?->txn_count ?? 0),
        ];
    }

    /**
     * @return array{amount: string, count: int}
     */
    protected function sumDiningConfirmed(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        PaymentMethod $method,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $row = DiningSession::query()
            ->where('payment_method', $method)
            ->where('payment_status', PaymentStatus::Confirmed)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as amount_sum')
            ->first();

        return [
            'amount' => $this->asMoney($row?->amount_sum),
            'count' => (int) ($row?->txn_count ?? 0),
        ];
    }

    /**
     * @return array{amount: string, count: int}
     */
    protected function sumConfirmedByMethod(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        PaymentMethod $method,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $retail = Order::query()
            ->whereNull('dining_session_id')
            ->where('payment_method', $method)
            ->where('payment_status', PaymentStatus::Confirmed)
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
            ->whereBetween('payment_confirmed_at', [$from, $to])
            ->count();

        $dining = DiningSession::query()
            ->where('payment_method', $method)
            ->where('payment_status', PaymentStatus::Confirmed)
            ->whereBetween('paid_at', [$from, $to])
            ->count();

        return [
            'amount' => '0.00',
            'count' => $retail + $dining,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function gstReport(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $channel,
        string $paymentMethod,
        string $taxable,
        string $tax,
    ): array {
        $inclusive = 0;
        $exclusive = 0;

        if ($channel !== self::CHANNEL_DINING) {
            $inclusive += (int) $this->retailRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)
                ->where('tax_enabled_snapshot', true)
                ->where('tax_inclusive_snapshot', true)
                ->count();
            $exclusive += (int) $this->retailRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)
                ->where('tax_enabled_snapshot', true)
                ->where('tax_inclusive_snapshot', false)
                ->count();
        }

        if (! in_array($channel, [self::CHANNEL_TAKEAWAY, self::CHANNEL_DELIVERY], true)) {
            $inclusive += (int) $this->diningRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)
                ->where('tax_enabled_snapshot', true)
                ->where('tax_inclusive_snapshot', true)
                ->count();
            $exclusive += (int) $this->diningRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)
                ->where('tax_enabled_snapshot', true)
                ->where('tax_inclusive_snapshot', false)
                ->count();
        }

        return [
            'taxable_base' => $taxable,
            'gst_amount' => $tax,
            'total_gst_collected' => $tax,
            'inclusive_transaction_count' => $inclusive,
            'exclusive_transaction_count' => $exclusive,
            'note' => 'GST uses immutable order/session tax snapshots only. Current Website Settings tax percent is ignored.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function discountReport(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $channel,
        string $paymentMethod,
    ): array {
        $promotion = '0.00';
        $referralCoupon = '0.00';
        $freeDrink = '0.00';

        if ($channel !== self::CHANNEL_DINING) {
            $orderIds = $this->retailRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)->pluck('id');

            if ($orderIds->isNotEmpty()) {
                $promotion = $this->asMoney(
                    OrderPromotion::query()->whereIn('order_id', $orderIds)->sum('discount_amount'),
                );
                $referralCoupon = $this->asMoney(
                    OrderRewardRedemption::query()
                        ->whereIn('order_id', $orderIds)
                        ->where('reward_type', CustomerRewardType::Coupon)
                        ->sum('benefit_amount'),
                );
                $freeDrink = $this->asMoney(
                    OrderRewardRedemption::query()
                        ->whereIn('order_id', $orderIds)
                        ->where('reward_type', CustomerRewardType::FreeDrink)
                        ->sum('benefit_amount'),
                );
            }
        }

        if (! in_array($channel, [self::CHANNEL_TAKEAWAY, self::CHANNEL_DELIVERY], true)) {
            $sessionIds = $this->diningRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)->pluck('id');

            if ($sessionIds->isNotEmpty()) {
                $promotion = $this->addMoney(
                    $promotion,
                    $this->asMoney(
                        DiningSessionPromotion::query()
                            ->whereIn('dining_session_id', $sessionIds)
                            ->sum('discount_amount'),
                    ),
                );
            }
        }

        $totalDiscount = $this->addMoney(
            $this->aggregateRetail($startUtc, $endUtc, $channel, $paymentMethod)['discounts'],
            $this->aggregateDining($startUtc, $endUtc, $channel, $paymentMethod)['discounts'],
        );
        $accounted = $this->addMoney($this->addMoney($promotion, $referralCoupon), $freeDrink);
        $other = $this->subMoney($totalDiscount, $accounted);

        if (bccomp($other, '0', 2) < 0) {
            $other = '0.00';
        }

        return [
            'promotion_discounts' => $promotion,
            'referral_coupon_discounts' => $referralCoupon,
            'free_drink_benefit_value' => $freeDrink,
            'other_discount_totals' => $other,
            'total_discounts' => $totalDiscount,
            'note' => 'Free-drink benefit value is informational and does not rewrite GST taxable snapshots.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function cancellationReport(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $channel,
        string $paymentMethod,
    ): array {
        $cancelled = ['count' => 0, 'value' => '0.00'];
        $rejected = ['count' => 0, 'value' => '0.00'];
        $paidExceptions = ['count' => 0, 'value' => '0.00'];

        if ($channel !== self::CHANNEL_DINING) {
            [$from, $to] = $this->utcRange($startUtc, $endUtc);

            $base = Order::query()
                ->whereNull('dining_session_id')
                ->when($channel === self::CHANNEL_TAKEAWAY, fn (Builder $q) => $q->where('fulfilment_method', OrderFulfilmentMethod::Takeaway))
                ->when($channel === self::CHANNEL_DELIVERY, fn (Builder $q) => $q->where('fulfilment_method', OrderFulfilmentMethod::Delivery))
                ->when($channel === self::CHANNEL_ALL, fn (Builder $q) => $q->whereIn('fulfilment_method', [
                    OrderFulfilmentMethod::Takeaway,
                    OrderFulfilmentMethod::Delivery,
                ]))
                ->when($paymentMethod === self::PAYMENT_CASH, fn (Builder $q) => $q->where('payment_method', PaymentMethod::Cash))
                ->when($paymentMethod === self::PAYMENT_UPI, fn (Builder $q) => $q->where('payment_method', PaymentMethod::Manual))
                ->whereBetween('placed_at', [$from, $to]);

            $cancelledRow = (clone $base)->where('status', OrderStatus::Cancelled)
                ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as amount_sum')
                ->first();
            $rejectedRow = (clone $base)->where('status', OrderStatus::Rejected)
                ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as amount_sum')
                ->first();
            $paidExceptionRow = (clone $base)
                ->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
                ->where('payment_status', PaymentStatus::Confirmed)
                ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as amount_sum')
                ->first();

            $cancelled = ['count' => (int) ($cancelledRow?->txn_count ?? 0), 'value' => $this->asMoney($cancelledRow?->amount_sum)];
            $rejected = ['count' => (int) ($rejectedRow?->txn_count ?? 0), 'value' => $this->asMoney($rejectedRow?->amount_sum)];
            $paidExceptions = ['count' => (int) ($paidExceptionRow?->txn_count ?? 0), 'value' => $this->asMoney($paidExceptionRow?->amount_sum)];
        }

        return [
            'cancelled' => $cancelled,
            'rejected' => $rejected,
            'paid_cancellation_exceptions' => $paidExceptions,
            'note' => 'Cancelled/rejected values are operational only and excluded from paid revenue. Paid cancellations without refund support are listed separately.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function transactionRows(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $channel,
        string $paymentMethod,
        ?int $limit,
    ): array {
        $timezone = $this->cafeAvailability->timezone();
        $rows = [];

        if ($channel !== self::CHANNEL_DINING) {
            $orders = $this->retailRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)
                ->orderBy('payment_confirmed_at')
                ->orderBy('id')
                ->when($limit !== null, fn (Builder $q) => $q->limit($limit))
                ->get([
                    'id',
                    'order_number',
                    'fulfilment_method',
                    'payment_method',
                    'payment_status',
                    'subtotal',
                    'discount_total',
                    'tax_amount',
                    'total_amount',
                    'payment_confirmed_at',
                ]);

            foreach ($orders as $order) {
                $rows[] = [
                    'type' => 'order',
                    'id' => $order->id,
                    'date_time' => optional($order->payment_confirmed_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                    'reference' => $order->order_number,
                    'channel' => $order->fulfilment_method?->value ?? '',
                    'payment_method' => $order->payment_method?->value ?? '',
                    'subtotal' => $this->asMoney($order->subtotal),
                    'discount' => $this->asMoney($order->discount_total),
                    'gst' => $this->asMoney($order->tax_amount),
                    'final_total' => $this->asMoney($order->total_amount),
                    'payment_status' => $order->payment_status?->value ?? '',
                    'url' => route('administrator.orders.show', $order),
                ];
            }
        }

        if (! in_array($channel, [self::CHANNEL_TAKEAWAY, self::CHANNEL_DELIVERY], true)) {
            $remaining = $limit !== null ? max(0, $limit - count($rows)) : null;

            if ($remaining === null || $remaining > 0) {
                $sessions = $this->diningRevenueQuery($startUtc, $endUtc, $channel, $paymentMethod)
                    ->orderBy('paid_at')
                    ->orderBy('id')
                    ->when($remaining !== null, fn (Builder $q) => $q->limit($remaining))
                    ->get([
                        'id',
                        'session_number',
                        'payment_method',
                        'payment_status',
                        'subtotal_amount',
                        'discount_amount',
                        'tax_amount',
                        'total_amount',
                        'paid_at',
                    ]);

                foreach ($sessions as $session) {
                    $rows[] = [
                        'type' => 'dining_session',
                        'id' => $session->id,
                        'date_time' => optional($session->paid_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                        'reference' => $session->session_number,
                        'channel' => 'dining',
                        'payment_method' => $session->payment_method?->value ?? '',
                        'subtotal' => $this->asMoney($session->subtotal_amount),
                        'discount' => $this->asMoney($session->discount_amount),
                        'gst' => $this->asMoney($session->tax_amount),
                        'final_total' => $this->asMoney($session->total_amount),
                        'payment_status' => $session->payment_status?->value ?? '',
                        'url' => route('administrator.dining-sessions.show', $session),
                    ];
                }
            }
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $a['date_time'], (string) $b['date_time']));

        return $rows;
    }

    protected function retailRevenueQuery(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $channel,
        string $paymentMethod,
    ): Builder {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        return Order::query()
            ->whereNull('dining_session_id')
            ->where('payment_status', PaymentStatus::Confirmed)
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
            ->whereBetween('payment_confirmed_at', [$from, $to])
            ->when($channel === self::CHANNEL_TAKEAWAY, fn (Builder $q) => $q->where('fulfilment_method', OrderFulfilmentMethod::Takeaway))
            ->when($channel === self::CHANNEL_DELIVERY, fn (Builder $q) => $q->where('fulfilment_method', OrderFulfilmentMethod::Delivery))
            ->when($channel === self::CHANNEL_ALL, fn (Builder $q) => $q->whereIn('fulfilment_method', [
                OrderFulfilmentMethod::Takeaway,
                OrderFulfilmentMethod::Delivery,
            ]))
            ->when($paymentMethod === self::PAYMENT_CASH, fn (Builder $q) => $q->where('payment_method', PaymentMethod::Cash))
            ->when($paymentMethod === self::PAYMENT_UPI, fn (Builder $q) => $q->where('payment_method', PaymentMethod::Manual));
    }

    protected function diningRevenueQuery(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $channel,
        string $paymentMethod,
    ): Builder {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        return DiningSession::query()
            ->where('payment_status', PaymentStatus::Confirmed)
            ->whereBetween('paid_at', [$from, $to])
            ->when($paymentMethod === self::PAYMENT_CASH, fn (Builder $q) => $q->where('payment_method', PaymentMethod::Cash))
            ->when($paymentMethod === self::PAYMENT_UPI, fn (Builder $q) => $q->where('payment_method', PaymentMethod::Manual));
    }

    /**
     * @return array{gross: string, discounts: string, tax: string, taxable: string, net: string, count: int}
     */
    protected function emptyAggregate(): array
    {
        return [
            'gross' => '0.00',
            'discounts' => '0.00',
            'tax' => '0.00',
            'taxable' => '0.00',
            'net' => '0.00',
            'count' => 0,
        ];
    }

    protected function normalizeChannel(?string $channel): string
    {
        $channel = $channel ?: self::CHANNEL_ALL;

        return in_array($channel, [
            self::CHANNEL_ALL,
            self::CHANNEL_TAKEAWAY,
            self::CHANNEL_DELIVERY,
            self::CHANNEL_DINING,
        ], true) ? $channel : self::CHANNEL_ALL;
    }

    protected function normalizePaymentMethod(?string $method): string
    {
        $method = $method ?: self::PAYMENT_ALL;

        return in_array($method, [self::PAYMENT_ALL, self::PAYMENT_CASH, self::PAYMENT_UPI], true)
            ? $method
            : self::PAYMENT_ALL;
    }

    protected function asMoney(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    protected function addMoney(string $left, string $right): string
    {
        return $this->asMoney(bcadd($left, $right, 2));
    }

    protected function subMoney(string $left, string $right): string
    {
        return $this->asMoney(bcsub($left, $right, 2));
    }

    protected function divideMoney(string $numerator, string $denominator): string
    {
        if (bccomp($denominator, '0', 2) === 0) {
            return '0.00';
        }

        return $this->asMoney(bcdiv($numerator, $denominator, 4));
    }
}
