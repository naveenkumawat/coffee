<?php

namespace App\Services\Loyalty;

use App\Enums\BehaviourEventType;
use App\Enums\LoyaltyTransactionType;
use App\Models\CustomerBehaviourEvent;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\User;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoyaltyReportingService implements LoyaltyReportingServiceInterface
{
    public const PRESET_TODAY = 'today';

    public const PRESET_YESTERDAY = 'yesterday';

    public const PRESET_LAST_7_DAYS = 'last_7_days';

    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_CUSTOM = 'custom';

    public function __construct(
        protected CafeAvailabilityServiceInterface $cafeAvailability,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildOperationsDashboard(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters);
        $start = $period['start_utc'];
        $end = $period['end_utc'];

        $earn = $this->aggregateEarn($start, $end);
        $redeem = $this->aggregateRedeem($start, $end);
        $restore = $this->aggregateRestore($start, $end);
        $earnReversal = $this->aggregateEarnReversal($start, $end);
        $adjustments = $this->aggregateAdjustments($start, $end);
        $outstanding = $this->outstandingSnapshot();
        $topRewards = $this->topRedeemedRewards($start, $end, 10);
        $rewardPerformance = $this->rewardPerformance($start, $end);
        $rewardTypeBreakdown = $this->redeemTypeBreakdown($start, $end);

        $qualifyingOrders = (int) $earn['order_count'];
        $redemptionCount = (int) $redeem['redemption_count'];
        $redemptionRate = $qualifyingOrders > 0
            ? round(($redemptionCount / $qualifyingOrders) * 100, 1)
            : null;

        return [
            'timezone' => $period['timezone'],
            'preset' => $period['preset'],
            'start_local' => $period['start_local'],
            'end_local' => $period['end_local'],
            'start_utc' => $start,
            'end_utc' => $end,
            'definitions' => $this->definitions(),
            'summary' => [
                'active_accounts' => LoyaltyAccount::query()->count(),
                'earned_points' => (int) $earn['points'],
                'earning_customers' => (int) $earn['customers'],
                'qualifying_orders' => $qualifyingOrders,
                'average_earned_per_order' => $qualifyingOrders > 0
                    ? round(((int) $earn['points']) / $qualifyingOrders, 1)
                    : null,
                'redeemed_points' => (int) $redeem['points'],
                'redemption_count' => $redemptionCount,
                'redeeming_customers' => (int) $redeem['customers'],
                'restored_points' => (int) $restore['points'],
                'restore_count' => (int) $restore['count'],
                'restore_customers' => (int) $restore['customers'],
                'reversed_earn_points' => (int) $earnReversal['points'],
                'reversed_earn_count' => (int) $earnReversal['count'],
                'reversed_earn_customers' => (int) $earnReversal['customers'],
                'adjustment_positive_points' => (int) $adjustments['positive_points'],
                'adjustment_negative_points' => (int) $adjustments['negative_points'],
                'adjustment_net_points' => (int) $adjustments['net_points'],
                'adjustment_count' => (int) $adjustments['count'],
                'redemption_rate_percent' => $redemptionRate,
                'outstanding_points' => (int) $outstanding['outstanding_points'],
                'positive_balance_customers' => (int) $outstanding['positive_balance_customers'],
                'average_positive_balance' => $outstanding['average_positive_balance'],
                'debt_customers' => (int) $outstanding['debt_customers'],
                'debt_points' => (int) $outstanding['debt_points'],
            ],
            'earn' => $earn,
            'redeem' => $redeem,
            'restore' => $restore,
            'earn_reversal' => $earnReversal,
            'adjustments' => $adjustments,
            'outstanding' => $outstanding,
            'top_redeemed_rewards' => $topRewards,
            'reward_type_breakdown' => $rewardTypeBreakdown,
            'reward_performance' => $rewardPerformance,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateLedger(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $period = $this->resolvePeriod($filters);
        $query = LoyaltyPointTransaction::query()
            ->with(['customer:id,name,email'])
            ->whereBetween('occurred_at', [$period['start_utc'], $period['end_utc']])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $type = trim((string) ($filters['transaction_type'] ?? ''));
        if ($type !== '' && $type !== 'all') {
            if ($type === 'restore') {
                $query->where('type', LoyaltyTransactionType::Reversal->value)
                    ->where('reason_code', 'order_loyalty_restore');
            } elseif ($type === 'earn_reversal') {
                $query->where('type', LoyaltyTransactionType::Reversal->value)
                    ->where('reason_code', 'order_earn_reversal');
            } else {
                $query->where('type', $type);
            }
        }

        $rewardId = (int) ($filters['reward_id'] ?? 0);
        if ($rewardId > 0) {
            $query->where('metadata->reward_id', $rewardId);
        }

        $customerId = (int) ($filters['customer_id'] ?? 0);
        if ($customerId > 0) {
            $query->where('customer_id', $customerId);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->whereHas('customer', function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdjustments(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $period = $this->resolvePeriod($filters);

        return LoyaltyPointTransaction::query()
            ->with(['customer:id,name,email'])
            ->where('type', LoyaltyTransactionType::Adjustment->value)
            ->whereBetween('occurred_at', [$period['start_utc'], $period['end_utc']])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function customerLoyaltyDetail(User $customer): array
    {
        $account = LoyaltyAccount::query()->where('customer_id', $customer->getKey())->first();
        $available = (int) ($account?->available_points ?? 0);
        $inDebt = $available < 0;

        $transactions = LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $debtCause = null;
        if ($inDebt) {
            $debtCause = $transactions->first(function (LoyaltyPointTransaction $txn): bool {
                $type = $txn->type instanceof LoyaltyTransactionType
                    ? $txn->type
                    : LoyaltyTransactionType::tryFrom((string) $txn->type);

                return ($type === LoyaltyTransactionType::Reversal && $txn->reason_code === 'order_earn_reversal')
                    || ($type === LoyaltyTransactionType::Adjustment && (int) $txn->points < 0);
            });
        }

        return [
            'account' => $account,
            'available_points' => $available,
            'display_available_points' => max(0, $available),
            'has_points_debt' => $inDebt,
            'debt_points' => $inDebt ? abs($available) : 0,
            'redemption_blocked' => $inDebt,
            'debt_cause' => $debtCause,
            'transactions' => $transactions,
            'redemptions' => $transactions->filter(function (LoyaltyPointTransaction $txn): bool {
                $type = $txn->type instanceof LoyaltyTransactionType
                    ? $txn->type
                    : LoyaltyTransactionType::tryFrom((string) $txn->type);

                return $type === LoyaltyTransactionType::Redeem;
            })->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportLedgerCsv(array $filters = []): StreamedResponse
    {
        $period = $this->resolvePeriod($filters);
        $filename = sprintf(
            'loyalty-ledger-%s-to-%s.csv',
            $period['start_local']->format('Ymd'),
            $period['end_local']->format('Ymd'),
        );

        return response()->streamDownload(function () use ($filters, $period): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'occurred_at',
                'type',
                'points',
                'customer_id',
                'customer_name',
                'reason_code',
                'description',
                'order_number',
                'reward_id',
                'transaction_id',
            ]);

            LoyaltyPointTransaction::query()
                ->with(['customer:id,name'])
                ->whereBetween('occurred_at', [$period['start_utc'], $period['end_utc']])
                ->when(
                    filled($filters['transaction_type'] ?? null) && ($filters['transaction_type'] ?? '') !== 'all',
                    function (Builder $query) use ($filters): void {
                        $type = (string) $filters['transaction_type'];
                        if ($type === 'restore') {
                            $query->where('type', LoyaltyTransactionType::Reversal->value)
                                ->where('reason_code', 'order_loyalty_restore');
                        } elseif ($type === 'earn_reversal') {
                            $query->where('type', LoyaltyTransactionType::Reversal->value)
                                ->where('reason_code', 'order_earn_reversal');
                        } else {
                            $query->where('type', $type);
                        }
                    },
                )
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->chunk(500, function (Collection $rows) use ($handle): void {
                    foreach ($rows as $txn) {
                        $metadata = is_array($txn->metadata) ? $txn->metadata : [];
                        fputcsv($handle, [
                            $txn->occurred_at?->toIso8601String(),
                            $txn->type instanceof LoyaltyTransactionType ? $txn->type->value : (string) $txn->type,
                            (int) $txn->points,
                            (int) $txn->customer_id,
                            $txn->customer?->name,
                            $txn->reason_code,
                            $txn->description,
                            $metadata['order_number'] ?? null,
                            $metadata['reward_id'] ?? null,
                            (int) $txn->getKey(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportBalancesCsv(array $filters = []): StreamedResponse
    {
        $debtOnly = ($filters['balance_filter'] ?? '') === 'debt';
        $positiveOnly = ($filters['balance_filter'] ?? '') === 'positive';

        return response()->streamDownload(function () use ($debtOnly, $positiveOnly): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'customer_id',
                'customer_name',
                'available_points',
                'lifetime_earned_points',
                'lifetime_redeemed_points',
                'lifetime_adjusted_points',
                'debt',
            ]);

            LoyaltyAccount::query()
                ->with(['customer:id,name'])
                ->when($debtOnly, fn (Builder $q) => $q->where('available_points', '<', 0))
                ->when($positiveOnly, fn (Builder $q) => $q->where('available_points', '>', 0))
                ->orderBy('customer_id')
                ->chunk(500, function (Collection $rows) use ($handle): void {
                    foreach ($rows as $account) {
                        fputcsv($handle, [
                            (int) $account->customer_id,
                            $account->customer?->name,
                            (int) $account->available_points,
                            (int) $account->lifetime_earned_points,
                            (int) $account->lifetime_redeemed_points,
                            (int) $account->lifetime_adjusted_points,
                            (int) $account->available_points < 0 ? 'yes' : 'no',
                        ]);
                    }
                });

            fclose($handle);
        }, 'loyalty-balances-'.now()->format('Ymd').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportRedemptionsCsv(array $filters = []): StreamedResponse
    {
        $period = $this->resolvePeriod($filters);
        $filename = sprintf(
            'loyalty-redemptions-%s-to-%s.csv',
            $period['start_local']->format('Ymd'),
            $period['end_local']->format('Ymd'),
        );

        return response()->streamDownload(function () use ($period): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'occurred_at',
                'customer_id',
                'customer_name',
                'points',
                'reward_id',
                'order_number',
                'discount_amount',
                'transaction_id',
            ]);

            LoyaltyPointTransaction::query()
                ->with(['customer:id,name'])
                ->where('type', LoyaltyTransactionType::Redeem->value)
                ->where('reason_code', 'order_loyalty_redeem')
                ->whereBetween('occurred_at', [$period['start_utc'], $period['end_utc']])
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->chunk(500, function (Collection $rows) use ($handle): void {
                    foreach ($rows as $txn) {
                        $metadata = is_array($txn->metadata) ? $txn->metadata : [];
                        fputcsv($handle, [
                            $txn->occurred_at?->toIso8601String(),
                            (int) $txn->customer_id,
                            $txn->customer?->name,
                            abs((int) $txn->points),
                            $metadata['reward_id'] ?? null,
                            $metadata['order_number'] ?? null,
                            $metadata['discount_amount'] ?? null,
                            (int) $txn->getKey(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
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
        $preset = (string) ($filters['preset'] ?? self::PRESET_LAST_7_DAYS);

        if (! in_array($preset, [
            self::PRESET_TODAY,
            self::PRESET_YESTERDAY,
            self::PRESET_LAST_7_DAYS,
            self::PRESET_THIS_MONTH,
            self::PRESET_CUSTOM,
        ], true)) {
            $preset = self::PRESET_LAST_7_DAYS;
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
     * @return array<string, string>
     */
    public function definitions(): array
    {
        return [
            'earned_points' => 'Sum of positive earn ledger points in range (not reduced by later reversals).',
            'redeemed_points' => 'Absolute sum of redeem ledger points in range.',
            'restored_points' => 'Sum of redemption restore (reversal) points in range.',
            'reversed_earn_points' => 'Absolute sum of earn-reversal points in range.',
            'outstanding_points' => 'Sum of max(available_points, 0) across accounts (points, not currency).',
            'debt_points' => 'Absolute sum of min(available_points, 0) across accounts.',
            'redemption_rate' => 'Redemptions in range ÷ qualifying earn orders in range (— when zero denominator).',
        ];
    }

    /**
     * @return array{points: int, customers: int, order_count: int}
     */
    protected function aggregateEarn(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = LoyaltyPointTransaction::query()
            ->where('type', LoyaltyTransactionType::Earn->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(points), 0) as points')
            ->selectRaw('COUNT(DISTINCT customer_id) as customers')
            ->selectRaw('COUNT(DISTINCT source_id) as order_count')
            ->first();

        return [
            'points' => (int) ($row->points ?? 0),
            'customers' => (int) ($row->customers ?? 0),
            'order_count' => (int) ($row->order_count ?? 0),
        ];
    }

    /**
     * @return array{points: int, customers: int, redemption_count: int}
     */
    protected function aggregateRedeem(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = LoyaltyPointTransaction::query()
            ->where('type', LoyaltyTransactionType::Redeem->value)
            ->where('reason_code', 'order_loyalty_redeem')
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(points)), 0) as points')
            ->selectRaw('COUNT(DISTINCT customer_id) as customers')
            ->selectRaw('COUNT(*) as redemption_count')
            ->first();

        return [
            'points' => (int) ($row->points ?? 0),
            'customers' => (int) ($row->customers ?? 0),
            'redemption_count' => (int) ($row->redemption_count ?? 0),
        ];
    }

    /**
     * @return array{points: int, count: int, customers: int}
     */
    protected function aggregateRestore(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = LoyaltyPointTransaction::query()
            ->where('type', LoyaltyTransactionType::Reversal->value)
            ->where('reason_code', 'order_loyalty_restore')
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(points), 0) as points')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COUNT(DISTINCT customer_id) as customers')
            ->first();

        return [
            'points' => (int) ($row->points ?? 0),
            'count' => (int) ($row->cnt ?? 0),
            'customers' => (int) ($row->customers ?? 0),
        ];
    }

    /**
     * @return array{points: int, count: int, customers: int}
     */
    protected function aggregateEarnReversal(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = LoyaltyPointTransaction::query()
            ->where('type', LoyaltyTransactionType::Reversal->value)
            ->where('reason_code', 'order_earn_reversal')
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(points)), 0) as points')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COUNT(DISTINCT customer_id) as customers')
            ->first();

        return [
            'points' => (int) ($row->points ?? 0),
            'count' => (int) ($row->cnt ?? 0),
            'customers' => (int) ($row->customers ?? 0),
        ];
    }

    /**
     * @return array{positive_points: int, negative_points: int, net_points: int, count: int}
     */
    protected function aggregateAdjustments(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = LoyaltyPointTransaction::query()
            ->where('type', LoyaltyTransactionType::Adjustment->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) as positive_points')
            ->selectRaw('COALESCE(SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END), 0) as negative_points')
            ->selectRaw('COALESCE(SUM(points), 0) as net_points')
            ->selectRaw('COUNT(*) as cnt')
            ->first();

        return [
            'positive_points' => (int) ($row->positive_points ?? 0),
            'negative_points' => (int) ($row->negative_points ?? 0),
            'net_points' => (int) ($row->net_points ?? 0),
            'count' => (int) ($row->cnt ?? 0),
        ];
    }

    /**
     * @return array{
     *     outstanding_points: int,
     *     positive_balance_customers: int,
     *     average_positive_balance: float|null,
     *     debt_customers: int,
     *     debt_points: int
     * }
     */
    protected function outstandingSnapshot(): array
    {
        $row = LoyaltyAccount::query()
            ->selectRaw('COALESCE(SUM(CASE WHEN available_points > 0 THEN available_points ELSE 0 END), 0) as outstanding_points')
            ->selectRaw('COALESCE(SUM(CASE WHEN available_points > 0 THEN 1 ELSE 0 END), 0) as positive_balance_customers')
            ->selectRaw('COALESCE(SUM(CASE WHEN available_points < 0 THEN 1 ELSE 0 END), 0) as debt_customers')
            ->selectRaw('COALESCE(SUM(CASE WHEN available_points < 0 THEN ABS(available_points) ELSE 0 END), 0) as debt_points')
            ->first();

        $positiveCustomers = (int) ($row->positive_balance_customers ?? 0);
        $outstanding = (int) ($row->outstanding_points ?? 0);

        return [
            'outstanding_points' => $outstanding,
            'positive_balance_customers' => $positiveCustomers,
            'average_positive_balance' => $positiveCustomers > 0
                ? round($outstanding / $positiveCustomers, 1)
                : null,
            'debt_customers' => (int) ($row->debt_customers ?? 0),
            'debt_points' => (int) ($row->debt_points ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function topRedeemedRewards(CarbonImmutable $start, CarbonImmutable $end, int $limit = 10): array
    {
        $rewardIdExpr = $this->castJsonPathAsInteger('metadata', 'reward_id');

        $rows = LoyaltyPointTransaction::query()
            ->where('type', LoyaltyTransactionType::Redeem->value)
            ->where('reason_code', 'order_loyalty_redeem')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull(DB::raw($rewardIdExpr))
            ->selectRaw($rewardIdExpr.' as reward_id')
            ->selectRaw('COUNT(*) as redemption_count')
            ->selectRaw('COALESCE(SUM(ABS(points)), 0) as points_consumed')
            ->selectRaw('COUNT(DISTINCT customer_id) as unique_customers')
            ->groupBy(DB::raw($rewardIdExpr))
            ->orderByDesc('redemption_count')
            ->limit($limit)
            ->get();

        $rewardNames = LoyaltyReward::withTrashed()
            ->whereIn('id', $rows->pluck('reward_id')->filter()->all())
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($rewardNames): array {
            $rewardId = (int) $row->reward_id;

            return [
                'reward_id' => $rewardId,
                'name' => $rewardNames[$rewardId] ?? ('Reward #'.$rewardId),
                'redemption_count' => (int) $row->redemption_count,
                'points_consumed' => (int) $row->points_consumed,
                'unique_customers' => (int) $row->unique_customers,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function redeemTypeBreakdown(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = Order::query()
            ->whereNotNull('loyalty_reward_id')
            ->whereNotNull('loyalty_reward_points_cost_snapshot')
            ->whereBetween('placed_at', [$start, $end])
            ->selectRaw('loyalty_reward_type_snapshot as reward_type')
            ->selectRaw('COUNT(*) as redemption_count')
            ->selectRaw('COALESCE(SUM(loyalty_reward_points_cost_snapshot), 0) as points_consumed')
            ->selectRaw('COALESCE(SUM(loyalty_discount_amount), 0) as discount_value')
            ->groupBy('loyalty_reward_type_snapshot')
            ->orderByDesc('redemption_count')
            ->get();

        return $rows->map(static fn ($row): array => [
            'reward_type' => (string) ($row->reward_type ?: 'unknown'),
            'redemption_count' => (int) $row->redemption_count,
            'points_consumed' => (int) $row->points_consumed,
            'discount_value' => number_format((float) $row->discount_value, 2, '.', ''),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rewardPerformance(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rewardIdExpr = $this->castJsonPathAsInteger('metadata', 'reward_id');

        $views = CustomerBehaviourEvent::query()
            ->where('event_type', BehaviourEventType::LoyaltyRewardViewed->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw($rewardIdExpr.' as reward_id')
            ->selectRaw('COUNT(*) as views')
            ->groupBy(DB::raw($rewardIdExpr))
            ->pluck('views', 'reward_id');

        $selections = CustomerBehaviourEvent::query()
            ->where('event_type', BehaviourEventType::LoyaltyRewardSelected->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw($rewardIdExpr.' as reward_id')
            ->selectRaw('COUNT(*) as selections')
            ->groupBy(DB::raw($rewardIdExpr))
            ->pluck('selections', 'reward_id');

        $redeems = LoyaltyPointTransaction::query()
            ->where('type', LoyaltyTransactionType::Redeem->value)
            ->where('reason_code', 'order_loyalty_redeem')
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw($rewardIdExpr.' as reward_id')
            ->selectRaw('COUNT(*) as redemptions')
            ->selectRaw('COALESCE(SUM(ABS(points)), 0) as points_consumed')
            ->selectRaw('COUNT(DISTINCT customer_id) as unique_customers')
            ->groupBy(DB::raw($rewardIdExpr))
            ->get()
            ->keyBy('reward_id');

        $discounts = Order::query()
            ->whereNotNull('loyalty_reward_id')
            ->whereBetween('placed_at', [$start, $end])
            ->selectRaw('loyalty_reward_id as reward_id')
            ->selectRaw('COALESCE(SUM(loyalty_discount_amount), 0) as discount_value')
            ->groupBy('loyalty_reward_id')
            ->pluck('discount_value', 'reward_id');

        $rewardIds = collect($views->keys())
            ->merge($selections->keys())
            ->merge($redeems->keys())
            ->merge($discounts->keys())
            ->filter()
            ->unique()
            ->values();

        $names = LoyaltyReward::withTrashed()
            ->whereIn('id', $rewardIds->all())
            ->pluck('name', 'id');

        return $rewardIds->map(function ($rewardId) use ($views, $selections, $redeems, $discounts, $names): array {
            $id = (int) $rewardId;
            $viewCount = (int) ($views[$id] ?? 0);
            $selectCount = (int) ($selections[$id] ?? 0);
            $redeemRow = $redeems[$id] ?? null;
            $redemptionCount = (int) ($redeemRow->redemptions ?? 0);
            $viewToSelect = $viewCount > 0 ? round(($selectCount / $viewCount) * 100, 1) : null;
            $selectToRedeem = $selectCount > 0 ? round(($redemptionCount / $selectCount) * 100, 1) : null;
            $viewToRedeem = $viewCount > 0 ? round(($redemptionCount / $viewCount) * 100, 1) : null;

            return [
                'reward_id' => $id,
                'name' => $names[$id] ?? ('Reward #'.$id),
                'views' => $viewCount,
                'selections' => $selectCount,
                'redemptions' => $redemptionCount,
                'points_consumed' => (int) ($redeemRow->points_consumed ?? 0),
                'unique_customers' => (int) ($redeemRow->unique_customers ?? 0),
                'discount_value' => number_format((float) ($discounts[$id] ?? 0), 2, '.', ''),
                'view_to_select_percent' => $viewToSelect,
                'select_to_redeem_percent' => $selectToRedeem,
                'view_to_redeem_percent' => $viewToRedeem,
            ];
        })->sortByDesc('redemptions')->values()->all();
    }

    protected function jsonText(string $column, string $path): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "json_extract({$column}, '$.{$path}')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$path}'))";
    }

    protected function castJsonPathAsInteger(string $column, string $path): string
    {
        $expression = $this->jsonText($column, $path);
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "CAST({$expression} AS INTEGER)";
        }

        return "CAST({$expression} AS UNSIGNED)";
    }
}
