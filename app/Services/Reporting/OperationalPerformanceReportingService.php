<?php

namespace App\Services\Reporting;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductType;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPreparation;
use App\Models\ProductCategory;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalPerformanceReportingService implements OperationalPerformanceReportingServiceInterface
{
    public const PRESET_TODAY = 'today';

    public const PRESET_YESTERDAY = 'yesterday';

    public const PRESET_LAST_7_DAYS = 'last_7_days';

    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_CUSTOM = 'custom';

    public const SECTION_OVERVIEW = 'overview';

    public const SECTION_BAR = 'bar';

    public const SECTION_KITCHEN = 'kitchen';

    public const SECTION_MIXED = 'mixed';

    public const SECTION_DINING = 'dining';

    public const SECTION_LONG_RUNNING = 'long_running';

    public const SECTION_PRODUCTS = 'products';

    public const CHANNEL_ALL = 'all';

    public const CHANNEL_TAKEAWAY = 'takeaway';

    public const CHANNEL_DELIVERY = 'delivery';

    public const CHANNEL_DINING = 'dining';

    public function __construct(
        protected CafeAvailabilityServiceInterface $cafeAvailability,
    ) {}

    public function buildAdminReport(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters);
        $section = $this->normalizeSection($filters['section'] ?? null);
        $station = $this->normalizeStation($filters['station'] ?? null);
        $channel = $this->normalizeChannel($filters['channel'] ?? null);
        $productCategoryId = filled($filters['product_category_id'] ?? null) ? (int) $filters['product_category_id'] : null;
        $productType = $this->normalizeProductType($filters['product_type'] ?? null);

        $tickets = $this->loadTicketsInPeriod(
            $period['start_utc'],
            $period['end_utc'],
            $station,
            $channel,
        );

        $now = CarbonImmutable::now($period['timezone']);

        return [
            'timezone' => $period['timezone'],
            'preset' => $period['preset'],
            'start_local' => $period['start_local'],
            'end_local' => $period['end_local'],
            'start_utc' => $period['start_utc'],
            'end_utc' => $period['end_utc'],
            'section' => $section,
            'filters' => [
                'station' => $station,
                'channel' => $channel,
                'product_category_id' => $productCategoryId,
                'product_type' => $productType,
            ],
            'overview' => $this->buildOverview($tickets, $now),
            'stations' => [
                PreparationStation::Bar->value => $this->stationSummary(
                    $tickets->filter(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Bar),
                    $now,
                ),
                PreparationStation::Kitchen->value => $this->stationSummary(
                    $tickets->filter(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Kitchen),
                    $now,
                ),
            ],
            'mixed_orders' => $this->mixedOrderPerformance($period['start_utc'], $period['end_utc'], $channel),
            'retail_turnaround' => $this->retailTurnaround($period['start_utc'], $period['end_utc'], $channel),
            'dining_rounds' => $this->diningRoundPerformance($period['start_utc'], $period['end_utc']),
            'dining_sessions' => $this->diningSessionPerformance($period['start_utc'], $period['end_utc']),
            'long_running' => $this->longRunningSnapshot($now, $station),
            'cancellations' => $this->cancellationBreakdown($tickets),
            'products' => $this->productPrepBreakdown(
                $tickets,
                $productCategoryId,
                $productType,
                $station,
            ),
            'filter_options' => [
                'stations' => PreparationStation::options(),
                'channels' => [
                    self::CHANNEL_ALL => 'All channels',
                    self::CHANNEL_TAKEAWAY => 'Takeaway',
                    self::CHANNEL_DELIVERY => 'Delivery',
                    self::CHANNEL_DINING => 'Dining',
                ],
                'product_categories' => ProductCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
                'product_types' => ProductType::options(),
            ],
        ];
    }

    public function buildOperatorOverview(): array
    {
        $period = $this->resolvePeriod(['preset' => self::PRESET_TODAY]);
        $now = CarbonImmutable::now($period['timezone']);
        $tickets = $this->loadTicketsInPeriod($period['start_utc'], $period['end_utc'], null, null);
        $bar = $tickets->filter(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Bar);
        $kitchen = $tickets->filter(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Kitchen);
        $barSummary = $this->stationSummary($bar, $now);
        $kitchenSummary = $this->stationSummary($kitchen, $now);
        $live = $this->longRunningSnapshot($now, null);

        return [
            'timezone' => $period['timezone'],
            'start_local' => $period['start_local'],
            'end_local' => $period['end_local'],
            'bar' => [
                'pending' => $barSummary['currently_pending'],
                'preparing' => $barSummary['currently_preparing'],
                'avg_prep_seconds' => $barSummary['avg_preparation_seconds'],
                'oldest_active' => $live['oldest_active_bar'],
            ],
            'kitchen' => [
                'pending' => $kitchenSummary['currently_pending'],
                'preparing' => $kitchenSummary['currently_preparing'],
                'avg_prep_seconds' => $kitchenSummary['avg_preparation_seconds'],
                'oldest_active' => $live['oldest_active_kitchen'],
            ],
            'mixed_waiting' => $live['mixed_waiting_on_other_station'],
            'ready_to_serve_rounds' => $live['ready_to_serve_rounds'],
            'bill_requested_sessions' => $live['bill_requested_sessions'],
        ];
    }

    public function buildStationQueueContext(string $station): array
    {
        $stationEnum = PreparationStation::from($station);
        $timezone = $this->cafeAvailability->timezone();
        $now = CarbonImmutable::now($timezone);
        $tickets = OrderPreparation::query()
            ->with(['order.diningSession', 'order.items'])
            ->where('station', $stationEnum)
            ->whereIn('status', [
                OrderPreparationStatus::Pending,
                OrderPreparationStatus::Accepted,
                OrderPreparationStatus::Preparing,
                OrderPreparationStatus::Ready,
            ])
            ->orderBy('created_at')
            ->get();

        return [
            'timezone' => $timezone,
            'station' => $stationEnum->value,
            'now' => $now,
            'tickets' => $tickets->map(fn (OrderPreparation $ticket): array => $this->liveTicketTiming($ticket, $now))->all(),
        ];
    }

    public function buildWaiterSessionTiming(int $diningSessionId): array
    {
        $timezone = $this->cafeAvailability->timezone();
        $now = CarbonImmutable::now($timezone);
        $session = DiningSession::query()
            ->with(['orders.preparations', 'orders.items'])
            ->findOrFail($diningSessionId);

        $rounds = [];
        foreach ($session->orders as $order) {
            $active = $order->preparations->filter(
                fn (OrderPreparation $t): bool => $t->status !== OrderPreparationStatus::Cancelled,
            );
            $allReady = $active->isNotEmpty()
                && $active->every(fn (OrderPreparation $t): bool => $t->status === OrderPreparationStatus::Ready);
            $overallReadyAt = $allReady
                ? $this->maxTimestamp($active->pluck('ready_at')->all())
                : null;

            $rounds[] = [
                'order_id' => $order->id,
                'round_number' => $order->dining_round_number,
                'order_number' => $order->order_number,
                'placed_at' => $order->placed_at,
                'round_elapsed_seconds' => $this->secondsBetween(
                    $order->placed_at ? CarbonImmutable::parse($order->placed_at) : null,
                    $allReady && $overallReadyAt ? $overallReadyAt : $now,
                ),
                'all_ready' => $allReady,
                'ready_to_serve_age_seconds' => $allReady
                    ? $this->secondsBetween($overallReadyAt, $now)
                    : null,
            ];
        }

        $billRequestedAt = $session->billing_requested_at
            ? CarbonImmutable::parse($session->billing_requested_at)
            : null;

        return [
            'timezone' => $timezone,
            'session_id' => $session->id,
            'session_number' => $session->session_number,
            'status' => $session->status?->value,
            'opened_at' => $session->opened_at,
            'session_elapsed_seconds' => $this->secondsBetween(
                $session->opened_at ? CarbonImmutable::parse($session->opened_at) : null,
                $session->closed_at ? CarbonImmutable::parse($session->closed_at) : $now,
            ),
            'bill_requested_elapsed_seconds' => $billRequestedAt && ! $session->paid_at
                ? $this->secondsBetween($billRequestedAt, $now)
                : null,
            'rounds' => $rounds,
        ];
    }

    public function exportPreparationPerformanceCsv(array $filters = []): StreamedResponse
    {
        $report = $this->buildAdminReport($filters);
        $station = $report['filters']['station'];
        $channel = $report['filters']['channel'];
        $tickets = $this->loadTicketsInPeriod(
            $report['start_utc'],
            $report['end_utc'],
            $station,
            $channel,
        );

        $filename = sprintf(
            'preparation-performance-%s-to-%s.csv',
            $report['start_local']->format('Ymd'),
            $report['end_local']->format('Ymd'),
        );

        return response()->streamDownload(function () use ($tickets, $report): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'date_time',
                'order_number',
                'dining_round',
                'channel',
                'station',
                'product',
                'variant',
                'created_at',
                'accepted_at',
                'preparing_at',
                'ready_at',
                'queue_wait_seconds',
                'prep_seconds',
                'total_seconds',
                'status',
            ]);

            $timezone = $report['timezone'];

            foreach ($tickets as $ticket) {
                $metrics = $this->ticketMetrics($ticket);
                $items = $ticket->items();
                $product = $items->pluck('product_name')->unique()->implode(' | ');
                $variant = $items->pluck('variant_name')->filter()->unique()->implode(' | ');
                $order = $ticket->order;

                fputcsv($handle, [
                    optional($ticket->created_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                    $order?->order_number ?? '',
                    $order?->dining_round_number ?? '',
                    $order?->fulfilment_method?->value ?? '',
                    $ticket->station?->value ?? '',
                    $product,
                    $variant,
                    optional($ticket->created_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                    optional($ticket->accepted_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                    optional($ticket->preparing_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                    optional($ticket->ready_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                    $metrics['queue_wait_seconds'] ?? '',
                    $metrics['prep_seconds'] ?? '',
                    $metrics['total_seconds'] ?? '',
                    $ticket->status?->value ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportDiningPerformanceCsv(array $filters = []): StreamedResponse
    {
        $report = $this->buildAdminReport($filters);
        $rows = $report['dining_sessions']['rows'];

        $filename = sprintf(
            'dining-performance-%s-to-%s.csv',
            $report['start_local']->format('Ymd'),
            $report['end_local']->format('Ymd'),
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'session',
                'table',
                'round_count',
                'opened_at',
                'bill_requested_at',
                'payment_confirmed_at',
                'closed_at',
                'session_duration_seconds',
                'bill_request_to_payment_seconds',
                'payment_to_close_seconds',
                'occupancy_seconds',
                'avg_round_interval_seconds',
                'status',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['session_number'],
                    $row['table'],
                    $row['round_count'],
                    $row['opened_at'],
                    $row['bill_requested_at'],
                    $row['paid_at'],
                    $row['closed_at'],
                    $row['session_duration_seconds'] ?? '',
                    $row['bill_request_to_payment_seconds'] ?? '',
                    $row['payment_to_close_seconds'] ?? '',
                    $row['occupancy_seconds'] ?? '',
                    $row['avg_round_interval_seconds'] ?? '',
                    $row['status'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
     * @return Collection<int, OrderPreparation>
     */
    protected function loadTicketsInPeriod(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        ?string $station,
        ?string $channel,
    ): Collection {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        return OrderPreparation::query()
            ->with(['order.diningSession', 'order.items.product.category'])
            ->whereBetween('order_preparations.created_at', [$from, $to])
            ->when($station, fn (Builder $q) => $q->where('station', $station))
            ->when($channel && $channel !== self::CHANNEL_ALL, function (Builder $q) use ($channel): void {
                $q->whereHas('order', function (Builder $orderQuery) use ($channel): void {
                    if ($channel === self::CHANNEL_DINING) {
                        $orderQuery->where('fulfilment_method', OrderFulfilmentMethod::DineIn);
                    } elseif ($channel === self::CHANNEL_TAKEAWAY) {
                        $orderQuery->where('fulfilment_method', OrderFulfilmentMethod::Takeaway);
                    } elseif ($channel === self::CHANNEL_DELIVERY) {
                        $orderQuery->where('fulfilment_method', OrderFulfilmentMethod::Delivery);
                    }
                });
            })
            ->orderBy('order_preparations.created_at')
            ->get();
    }

    /**
     * @param  Collection<int, OrderPreparation>  $tickets
     * @return array<string, mixed>
     */
    protected function buildOverview(Collection $tickets, CarbonImmutable $now): array
    {
        $ready = $tickets->filter(fn (OrderPreparation $t): bool => $t->status === OrderPreparationStatus::Ready);
        $totals = $ready->map(fn (OrderPreparation $t): ?int => $this->ticketMetrics($t)['total_seconds'])->filter();

        return [
            'tickets_created' => $tickets->count(),
            'ready_tickets' => $ready->count(),
            'cancelled_tickets' => $tickets->where('status', OrderPreparationStatus::Cancelled)->count(),
            'avg_total_ticket_seconds' => $this->average($totals->all()),
            'max_total_ticket_seconds' => $totals->isEmpty() ? null : $totals->max(),
            'active_pending' => OrderPreparation::query()->where('status', OrderPreparationStatus::Pending)->count(),
            'active_preparing' => OrderPreparation::query()->where('status', OrderPreparationStatus::Preparing)->count(),
            'as_of' => $now->toDateTimeString(),
        ];
    }

    /**
     * @param  Collection<int, OrderPreparation>  $tickets
     * @return array<string, mixed>
     */
    protected function stationSummary(Collection $tickets, CarbonImmutable $now): array
    {
        $ready = $tickets->filter(fn (OrderPreparation $t): bool => $t->status === OrderPreparationStatus::Ready);
        $queueWaits = [];
        $startDelays = [];
        $preps = [];
        $totals = [];

        foreach ($ready as $ticket) {
            $metrics = $this->ticketMetrics($ticket);
            if ($metrics['queue_wait_seconds'] !== null) {
                $queueWaits[] = $metrics['queue_wait_seconds'];
            }
            if ($metrics['start_delay_seconds'] !== null) {
                $startDelays[] = $metrics['start_delay_seconds'];
            }
            if ($metrics['prep_seconds'] !== null) {
                $preps[] = $metrics['prep_seconds'];
            }
            if ($metrics['total_seconds'] !== null) {
                $totals[] = $metrics['total_seconds'];
            }
        }

        $station = $tickets->first()?->station;
        $livePending = $station
            ? OrderPreparation::query()->where('station', $station)->where('status', OrderPreparationStatus::Pending)->count()
            : 0;
        $livePreparing = $station
            ? OrderPreparation::query()->where('station', $station)->whereIn('status', [
                OrderPreparationStatus::Accepted,
                OrderPreparationStatus::Preparing,
            ])->count()
            : 0;

        return [
            'tickets' => $tickets->count(),
            'ready_tickets' => $ready->count(),
            'avg_queue_wait_seconds' => $this->average($queueWaits),
            'avg_start_delay_seconds' => $this->average($startDelays),
            'avg_preparation_seconds' => $this->average($preps),
            'avg_total_ticket_seconds' => $this->average($totals),
            'max_ticket_time_seconds' => $totals === [] ? null : max($totals),
            'currently_pending' => $livePending,
            'currently_preparing' => $livePreparing,
            'as_of' => $now->toDateTimeString(),
        ];
    }

    /**
     * @return array{
     *     queue_wait_seconds: ?int,
     *     start_delay_seconds: ?int,
     *     prep_seconds: ?int,
     *     total_seconds: ?int
     * }
     */
    public function ticketMetrics(OrderPreparation $ticket): array
    {
        $created = $ticket->created_at ? CarbonImmutable::parse($ticket->created_at) : null;
        $accepted = $ticket->accepted_at ? CarbonImmutable::parse($ticket->accepted_at) : null;
        $preparing = $ticket->preparing_at ? CarbonImmutable::parse($ticket->preparing_at) : null;
        $ready = $ticket->ready_at ? CarbonImmutable::parse($ticket->ready_at) : null;

        return [
            'queue_wait_seconds' => $this->secondsBetween($created, $accepted),
            'start_delay_seconds' => $this->secondsBetween($accepted, $preparing),
            'prep_seconds' => $this->secondsBetween($preparing, $ready),
            'total_seconds' => $this->secondsBetween($created, $ready),
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    protected function mixedOrderPerformance(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        ?string $channel,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $orders = Order::query()
            ->with('preparations')
            ->whereBetween('placed_at', [$from, $to])
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
            ->when($channel && $channel !== self::CHANNEL_ALL, function (Builder $q) use ($channel): void {
                if ($channel === self::CHANNEL_DINING) {
                    $q->where('fulfilment_method', OrderFulfilmentMethod::DineIn);
                } elseif ($channel === self::CHANNEL_TAKEAWAY) {
                    $q->where('fulfilment_method', OrderFulfilmentMethod::Takeaway);
                } elseif ($channel === self::CHANNEL_DELIVERY) {
                    $q->where('fulfilment_method', OrderFulfilmentMethod::Delivery);
                }
            })
            ->get()
            ->filter(function (Order $order): bool {
                $active = $order->preparations->filter(
                    fn (OrderPreparation $t): bool => $t->status !== OrderPreparationStatus::Cancelled,
                );

                return $active->pluck('station')->unique()->count() >= 2;
            });

        $rows = [];

        foreach ($orders as $order) {
            $active = $order->preparations->filter(
                fn (OrderPreparation $t): bool => $t->status !== OrderPreparationStatus::Cancelled,
            );
            $bar = $active->first(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Bar);
            $kitchen = $active->first(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Kitchen);
            $barReady = $bar?->ready_at ? CarbonImmutable::parse($bar->ready_at) : null;
            $kitchenReady = $kitchen?->ready_at ? CarbonImmutable::parse($kitchen->ready_at) : null;
            $allReady = $active->isNotEmpty()
                && $active->every(fn (OrderPreparation $t): bool => $t->status === OrderPreparationStatus::Ready);
            $overallReady = $allReady
                ? $this->maxTimestamp($active->pluck('ready_at')->all())
                : null;
            $earliestReady = $this->minTimestamp(array_filter([$barReady, $kitchenReady]));
            $latestReady = $this->maxTimestamp(array_filter([$barReady, $kitchenReady]));
            $blocking = null;
            if ($barReady && $kitchenReady) {
                $blocking = $barReady->gt($kitchenReady)
                    ? PreparationStation::Bar->value
                    : ($kitchenReady->gt($barReady) ? PreparationStation::Kitchen->value : null);
            } elseif ($barReady && ! $kitchenReady) {
                $blocking = PreparationStation::Kitchen->value;
            } elseif ($kitchenReady && ! $barReady) {
                $blocking = PreparationStation::Bar->value;
            }

            $rows[] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'channel' => $order->fulfilment_method?->value,
                'dining_round' => $order->dining_round_number,
                'bar_ready_at' => $barReady?->toDateTimeString(),
                'kitchen_ready_at' => $kitchenReady?->toDateTimeString(),
                'overall_ready_at' => $overallReady?->toDateTimeString(),
                'all_stations_ready' => $allReady,
                'station_gap_seconds' => $this->secondsBetween($earliestReady, $latestReady),
                'blocking_station' => $blocking,
            ];
        }

        return ['rows' => array_values($rows)];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, averages: array<string, ?int>}
     */
    protected function retailTurnaround(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        ?string $channel,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $orders = Order::query()
            ->with('preparations')
            ->whereNull('dining_session_id')
            ->whereBetween('placed_at', [$from, $to])
            ->when($channel && $channel !== self::CHANNEL_ALL && $channel !== self::CHANNEL_DINING, function (Builder $q) use ($channel): void {
                $q->where('fulfilment_method', $channel);
            })
            ->when($channel === self::CHANNEL_DINING, fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->get();

        $rows = [];
        $orderToAccept = [];
        $acceptToReady = [];
        $totalTurnaround = [];
        $readyToCompleted = [];

        foreach ($orders as $order) {
            if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                continue;
            }

            $created = $order->placed_at ? CarbonImmutable::parse($order->placed_at) : null;
            $accepted = $order->accepted_at ? CarbonImmutable::parse($order->accepted_at) : null;
            $ready = $order->ready_for_pickup_at ? CarbonImmutable::parse($order->ready_for_pickup_at) : null;
            $completed = $order->completed_at ? CarbonImmutable::parse($order->completed_at) : null;

            $active = $order->preparations->filter(
                fn (OrderPreparation $t): bool => $t->status !== OrderPreparationStatus::Cancelled,
            );
            $allReady = $active->isNotEmpty()
                && $active->every(fn (OrderPreparation $t): bool => $t->status === OrderPreparationStatus::Ready);
            $overallReady = $allReady
                ? $this->maxTimestamp($active->pluck('ready_at')->all())
                : $ready;

            $ota = $this->secondsBetween($created, $accepted);
            $atr = $this->secondsBetween($accepted, $overallReady);
            $total = $this->secondsBetween($created, $overallReady);
            $rtc = $this->secondsBetween($overallReady, $completed);

            if ($ota !== null) {
                $orderToAccept[] = $ota;
            }
            if ($atr !== null) {
                $acceptToReady[] = $atr;
            }
            if ($total !== null) {
                $totalTurnaround[] = $total;
            }
            if ($rtc !== null) {
                $readyToCompleted[] = $rtc;
            }

            $rows[] = [
                'order_number' => $order->order_number,
                'channel' => $order->fulfilment_method?->value,
                'order_to_accept_seconds' => $ota,
                'accept_to_ready_seconds' => $atr,
                'total_turnaround_seconds' => $total,
                'ready_to_completed_seconds' => $rtc,
                'all_stations_ready' => $allReady,
            ];
        }

        return [
            'rows' => $rows,
            'averages' => [
                'order_to_accept_seconds' => $this->average($orderToAccept),
                'accept_to_ready_seconds' => $this->average($acceptToReady),
                'total_turnaround_seconds' => $this->average($totalTurnaround),
                'ready_to_completed_seconds' => $this->average($readyToCompleted),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    protected function diningRoundPerformance(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $orders = Order::query()
            ->with('preparations')
            ->whereNotNull('dining_session_id')
            ->whereBetween('placed_at', [$from, $to])
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
            ->get();

        $rows = [];

        foreach ($orders as $order) {
            $active = $order->preparations->filter(
                fn (OrderPreparation $t): bool => $t->status !== OrderPreparationStatus::Cancelled,
            );
            $bar = $active->first(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Bar);
            $kitchen = $active->first(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Kitchen);
            $allReady = $active->isNotEmpty()
                && $active->every(fn (OrderPreparation $t): bool => $t->status === OrderPreparationStatus::Ready);
            $overallReady = $allReady
                ? $this->maxTimestamp($active->pluck('ready_at')->all())
                : null;
            $placed = $order->placed_at ? CarbonImmutable::parse($order->placed_at) : null;
            $barReady = $bar?->ready_at ? CarbonImmutable::parse($bar->ready_at) : null;
            $kitchenReady = $kitchen?->ready_at ? CarbonImmutable::parse($kitchen->ready_at) : null;

            $rows[] = [
                'session_id' => $order->dining_session_id,
                'order_number' => $order->order_number,
                'round_number' => $order->dining_round_number,
                'round_to_ready_seconds' => $this->secondsBetween($placed, $overallReady),
                'bar_prep_seconds' => $bar ? $this->ticketMetrics($bar)['prep_seconds'] : null,
                'kitchen_prep_seconds' => $kitchen ? $this->ticketMetrics($kitchen)['prep_seconds'] : null,
                'station_gap_seconds' => $this->secondsBetween(
                    $this->minTimestamp(array_filter([$barReady, $kitchenReady])),
                    $this->maxTimestamp(array_filter([$barReady, $kitchenReady])),
                ),
                'all_ready' => $allReady,
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    protected function diningSessionPerformance(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);
        $timezone = $this->cafeAvailability->timezone();

        $sessions = DiningSession::query()
            ->with(['orders' => fn ($q) => $q->orderBy('dining_round_number'), 'cafeTable'])
            ->whereBetween('opened_at', [$from, $to])
            ->where('status', '!=', DiningSessionStatus::Cancelled->value)
            ->orderBy('opened_at')
            ->get();

        $rows = [];

        foreach ($sessions as $session) {
            $opened = $session->opened_at ? CarbonImmutable::parse($session->opened_at) : null;
            $billRequested = $session->billing_requested_at ? CarbonImmutable::parse($session->billing_requested_at) : null;
            $paid = $session->paid_at ? CarbonImmutable::parse($session->paid_at) : null;
            $closed = $session->closed_at ? CarbonImmutable::parse($session->closed_at) : null;
            $rounds = $session->orders->sortBy('placed_at')->values();
            $roundCount = $rounds->count();
            $intervals = [];
            for ($i = 1; $i < $roundCount; $i++) {
                $prev = $rounds[$i - 1]->placed_at ? CarbonImmutable::parse($rounds[$i - 1]->placed_at) : null;
                $curr = $rounds[$i]->placed_at ? CarbonImmutable::parse($rounds[$i]->placed_at) : null;
                $seconds = $this->secondsBetween($prev, $curr);
                if ($seconds !== null) {
                    $intervals[] = $seconds;
                }
            }

            $occupancyEnd = $closed ?? $paid;

            $rows[] = [
                'session_number' => $session->session_number,
                'table' => $session->table_name_snapshot
                    ?? $session->cafeTable?->snapshotLabel()
                    ?? '—',
                'status' => $session->status instanceof DiningSessionStatus
                    ? $session->status->value
                    : (string) $session->status,
                'round_count' => $roundCount,
                'opened_at' => $opened?->timezone($timezone)->format('Y-m-d H:i:s') ?? '',
                'first_round_at' => optional($rounds->first()?->placed_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                'last_round_at' => optional($rounds->last()?->placed_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                'bill_requested_at' => $billRequested?->timezone($timezone)->format('Y-m-d H:i:s') ?? '',
                'paid_at' => $paid?->timezone($timezone)->format('Y-m-d H:i:s') ?? '',
                'closed_at' => $closed?->timezone($timezone)->format('Y-m-d H:i:s') ?? '',
                'session_duration_seconds' => $this->secondsBetween($opened, $closed),
                'bill_request_to_payment_seconds' => $this->secondsBetween($billRequested, $paid),
                'payment_to_close_seconds' => $this->secondsBetween($paid, $closed),
                'occupancy_seconds' => $this->secondsBetween($opened, $occupancyEnd),
                'avg_round_interval_seconds' => $this->average($intervals),
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    protected function longRunningSnapshot(CarbonImmutable $now, ?string $station): array
    {
        $activeQuery = OrderPreparation::query()
            ->with(['order.diningSession'])
            ->whereIn('status', [
                OrderPreparationStatus::Pending,
                OrderPreparationStatus::Accepted,
                OrderPreparationStatus::Preparing,
            ])
            ->when($station, fn (Builder $q) => $q->where('station', $station))
            ->orderBy('created_at');

        $active = $activeQuery->get();

        $pending = $active->where('status', OrderPreparationStatus::Pending)
            ->sortBy('created_at')
            ->take(10)
            ->values()
            ->map(fn (OrderPreparation $t): array => $this->liveTicketTiming($t, $now))
            ->all();

        $preparing = $active->filter(
            fn (OrderPreparation $t): bool => in_array($t->status, [
                OrderPreparationStatus::Accepted,
                OrderPreparationStatus::Preparing,
            ], true),
        )
            ->sortBy(fn (OrderPreparation $t) => $t->preparing_at ?? $t->accepted_at ?? $t->created_at)
            ->take(10)
            ->values()
            ->map(fn (OrderPreparation $t): array => $this->liveTicketTiming($t, $now))
            ->all();

        $oldestBar = $active->first(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Bar);
        $oldestKitchen = $active->first(fn (OrderPreparation $t): bool => $t->station === PreparationStation::Kitchen);

        $mixedWaiting = Order::query()
            ->with('preparations')
            ->whereNotIn('status', [
                OrderStatus::Cancelled,
                OrderStatus::Rejected,
                OrderStatus::Completed,
                OrderStatus::ReadyForPickup,
            ])
            ->whereHas('preparations', fn (Builder $q) => $q->where('status', OrderPreparationStatus::Ready))
            ->whereHas('preparations', fn (Builder $q) => $q->whereIn('status', [
                OrderPreparationStatus::Pending,
                OrderPreparationStatus::Accepted,
                OrderPreparationStatus::Preparing,
            ]))
            ->limit(20)
            ->get()
            ->filter(function (Order $order): bool {
                $activeTickets = $order->preparations->filter(
                    fn (OrderPreparation $t): bool => $t->status !== OrderPreparationStatus::Cancelled,
                );

                return $activeTickets->pluck('station')->unique()->count() >= 2;
            })
            ->map(function (Order $order): array {
                $ready = $order->preparations->filter(fn (OrderPreparation $t): bool => $t->status === OrderPreparationStatus::Ready);
                $waiting = $order->preparations->filter(
                    fn (OrderPreparation $t): bool => in_array($t->status, [
                        OrderPreparationStatus::Pending,
                        OrderPreparationStatus::Accepted,
                        OrderPreparationStatus::Preparing,
                    ], true),
                );

                return [
                    'order_number' => $order->order_number,
                    'ready_stations' => $ready->pluck('station')->map(fn ($s) => $s?->value)->values()->all(),
                    'waiting_stations' => $waiting->pluck('station')->map(fn ($s) => $s?->value)->values()->all(),
                ];
            })
            ->values()
            ->all();

        $readyToServe = Order::query()
            ->with(['diningSession', 'preparations'])
            ->where('fulfilment_method', OrderFulfilmentMethod::DineIn)
            ->where('status', OrderStatus::ReadyForPickup)
            ->whereNotNull('dining_session_id')
            ->orderBy('ready_for_pickup_at')
            ->limit(20)
            ->get()
            ->map(function (Order $order) use ($now): array {
                $readyAt = $order->ready_for_pickup_at
                    ? CarbonImmutable::parse($order->ready_for_pickup_at)
                    : null;

                return [
                    'order_number' => $order->order_number,
                    'session_number' => $order->diningSession?->session_number,
                    'table' => $order->diningSession?->table_name_snapshot,
                    'round_number' => $order->dining_round_number,
                    'ready_to_serve_age_seconds' => $this->secondsBetween($readyAt, $now),
                ];
            })
            ->all();

        $billRequested = DiningSession::query()
            ->whereIn('status', [
                DiningSessionStatus::BillingRequested,
                DiningSessionStatus::AwaitingPayment,
            ])
            ->orderBy('billing_requested_at')
            ->limit(20)
            ->get()
            ->map(function (DiningSession $session) use ($now): array {
                $requested = $session->billing_requested_at
                    ? CarbonImmutable::parse($session->billing_requested_at)
                    : null;

                return [
                    'session_number' => $session->session_number,
                    'table' => $session->table_name_snapshot,
                    'status' => $session->status?->value,
                    'bill_requested_elapsed_seconds' => $this->secondsBetween($requested, $now),
                ];
            })
            ->all();

        return [
            'longest_pending' => $pending,
            'longest_preparing' => $preparing,
            'oldest_active_bar' => $oldestBar ? $this->liveTicketTiming($oldestBar, $now) : null,
            'oldest_active_kitchen' => $oldestKitchen ? $this->liveTicketTiming($oldestKitchen, $now) : null,
            'station_backlog' => [
                'bar_pending' => OrderPreparation::query()->where('station', PreparationStation::Bar)->where('status', OrderPreparationStatus::Pending)->count(),
                'bar_preparing' => OrderPreparation::query()->where('station', PreparationStation::Bar)->whereIn('status', [OrderPreparationStatus::Accepted, OrderPreparationStatus::Preparing])->count(),
                'kitchen_pending' => OrderPreparation::query()->where('station', PreparationStation::Kitchen)->where('status', OrderPreparationStatus::Pending)->count(),
                'kitchen_preparing' => OrderPreparation::query()->where('station', PreparationStation::Kitchen)->whereIn('status', [OrderPreparationStatus::Accepted, OrderPreparationStatus::Preparing])->count(),
            ],
            'mixed_waiting_on_other_station' => $mixedWaiting,
            'ready_to_serve_rounds' => $readyToServe,
            'bill_requested_sessions' => $billRequested,
        ];
    }

    /**
     * @param  Collection<int, OrderPreparation>  $tickets
     * @return array{before_preparation: int, after_preparation_began: int}
     */
    protected function cancellationBreakdown(Collection $tickets): array
    {
        $cancelled = $tickets->filter(fn (OrderPreparation $t): bool => $t->status === OrderPreparationStatus::Cancelled);
        $before = $cancelled->filter(fn (OrderPreparation $t): bool => $t->preparing_at === null)->count();
        $after = $cancelled->filter(fn (OrderPreparation $t): bool => $t->preparing_at !== null)->count();

        return [
            'before_preparation' => $before,
            'after_preparation_began' => $after,
        ];
    }

    /**
     * @param  Collection<int, OrderPreparation>  $tickets
     * @return array{rows: list<array<string, mixed>>}
     */
    protected function productPrepBreakdown(
        Collection $tickets,
        ?int $productCategoryId,
        ?string $productType,
        ?string $station,
    ): array {
        $ready = $tickets->filter(fn (OrderPreparation $t): bool => $t->status === OrderPreparationStatus::Ready);
        $grouped = [];

        foreach ($ready as $ticket) {
            if ($station !== null && $ticket->station?->value !== $station) {
                continue;
            }

            $prepSeconds = $this->ticketMetrics($ticket)['prep_seconds'];
            if ($prepSeconds === null) {
                continue;
            }

            foreach ($ticket->items() as $item) {
                /** @var OrderItem $item */
                $product = $item->product;
                $type = $product?->product_type instanceof ProductType
                    ? $product->product_type->value
                    : (string) ($product?->product_type ?? '');

                if ($productType !== null && $type !== $productType) {
                    continue;
                }

                $categoryId = $product?->product_category_id;
                if ($productCategoryId !== null && (int) $categoryId !== $productCategoryId) {
                    continue;
                }

                $key = implode(':', [
                    (string) ($item->product_id ?? '0'),
                    (string) ($item->product_variant_id ?? '0'),
                    $ticket->station?->value ?? '',
                ]);

                $grouped[$key] ??= [
                    'product' => $item->product_name,
                    'variant' => $item->variant_name,
                    'category' => $product?->category?->name ?? '—',
                    'product_type' => $type !== '' ? $type : '—',
                    'station' => $ticket->station?->value ?? '—',
                    'samples' => [],
                ];
                $grouped[$key]['samples'][] = $prepSeconds;
            }
        }

        $rows = collect($grouped)->map(function (array $row): array {
            return [
                'product' => $row['product'],
                'variant' => $row['variant'],
                'category' => $row['category'],
                'product_type' => $row['product_type'],
                'station' => $row['station'],
                'ready_ticket_samples' => count($row['samples']),
                'avg_prep_seconds' => $this->average($row['samples']),
            ];
        })->sortByDesc('ready_ticket_samples')->values()->all();

        return ['rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveTicketTiming(OrderPreparation $ticket, CarbonImmutable $now): array
    {
        $queueStart = $ticket->created_at ? CarbonImmutable::parse($ticket->created_at) : null;
        $stageStart = match ($ticket->status) {
            OrderPreparationStatus::Pending => $queueStart,
            OrderPreparationStatus::Accepted => $ticket->accepted_at
                ? CarbonImmutable::parse($ticket->accepted_at)
                : $queueStart,
            OrderPreparationStatus::Preparing => $ticket->preparing_at
                ? CarbonImmutable::parse($ticket->preparing_at)
                : ($ticket->accepted_at ? CarbonImmutable::parse($ticket->accepted_at) : $queueStart),
            default => $queueStart,
        };

        return [
            'id' => $ticket->id,
            'order_number' => $ticket->order?->order_number,
            'station' => $ticket->station?->value,
            'status' => $ticket->status?->value,
            'queue_age_seconds' => $this->secondsBetween($queueStart, $now),
            'stage_elapsed_seconds' => $this->secondsBetween($stageStart, $now),
            'created_at' => optional($ticket->created_at)?->toDateTimeString(),
        ];
    }

    protected function secondsBetween(?CarbonInterface $from, ?CarbonInterface $to): ?int
    {
        if ($from === null || $to === null) {
            return null;
        }

        return (int) $from->diffInSeconds($to, false);
    }

    /**
     * @param  list<int|null|float>  $values
     */
    protected function average(array $values): ?int
    {
        $filtered = array_values(array_filter($values, fn ($v): bool => $v !== null));

        if ($filtered === []) {
            return null;
        }

        return (int) round(array_sum($filtered) / count($filtered));
    }

    /**
     * @param  list<mixed>  $timestamps
     */
    protected function maxTimestamp(array $timestamps): ?CarbonImmutable
    {
        $parsed = collect($timestamps)
            ->filter()
            ->map(fn ($ts): CarbonImmutable => CarbonImmutable::parse($ts));

        if ($parsed->isEmpty()) {
            return null;
        }

        return $parsed->sort()->last();
    }

    /**
     * @param  list<mixed>  $timestamps
     */
    protected function minTimestamp(array $timestamps): ?CarbonImmutable
    {
        $parsed = collect($timestamps)
            ->filter()
            ->map(fn ($ts): CarbonImmutable => CarbonImmutable::parse($ts));

        if ($parsed->isEmpty()) {
            return null;
        }

        return $parsed->sort()->first();
    }

    protected function normalizeSection(?string $section): string
    {
        $section = $section ?: self::SECTION_OVERVIEW;

        return in_array($section, [
            self::SECTION_OVERVIEW,
            self::SECTION_BAR,
            self::SECTION_KITCHEN,
            self::SECTION_MIXED,
            self::SECTION_DINING,
            self::SECTION_LONG_RUNNING,
            self::SECTION_PRODUCTS,
        ], true) ? $section : self::SECTION_OVERVIEW;
    }

    protected function normalizeStation(?string $station): ?string
    {
        if (! filled($station)) {
            return null;
        }

        return PreparationStation::tryFrom($station)?->value;
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

    protected function normalizeProductType(?string $type): ?string
    {
        if (! filled($type)) {
            return null;
        }

        return ProductType::tryFrom($type)?->value;
    }
}
