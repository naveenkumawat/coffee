<?php

namespace App\Services\Reporting;

use App\Enums\IngredientUnit;
use App\Enums\InventoryRefillRequestStatus;
use App\Enums\InventoryStockStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductType;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\OrderItem;
use App\Models\ProductCategory;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\OrderInventory\OrderInventoryConsumptionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryProductReportingService implements InventoryProductReportingServiceInterface
{
    public const PRESET_TODAY = 'today';

    public const PRESET_YESTERDAY = 'yesterday';

    public const PRESET_LAST_7_DAYS = 'last_7_days';

    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_CUSTOM = 'custom';

    public const SECTION_OVERVIEW = 'overview';

    public const SECTION_INGREDIENTS = 'ingredients';

    public const SECTION_PRODUCTS = 'products';

    public const SECTION_REFILLS = 'refills';

    public const SECTION_MOVEMENTS = 'movements';

    /**
     * @var list<string>
     */
    protected const RESTOCK_TYPES = [
        InventoryTransactionType::StockAdded->value,
        InventoryTransactionType::Purchase->value,
        InventoryTransactionType::ManualAddition->value,
    ];

    /**
     * @var list<string>
     */
    protected const ADJUSTMENT_TYPES = [
        InventoryTransactionType::ManualAdjustment->value,
        InventoryTransactionType::ManualReduction->value,
        InventoryTransactionType::Correction->value,
        InventoryTransactionType::Wastage->value,
        InventoryTransactionType::Damage->value,
        InventoryTransactionType::Expiry->value,
        InventoryTransactionType::OpeningBalance->value,
    ];

    public function __construct(
        protected CafeAvailabilityServiceInterface $cafeAvailability,
    ) {}

    public function buildAdminReport(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters);
        $section = $this->normalizeSection($filters['section'] ?? null);
        $ingredientId = filled($filters['ingredient_id'] ?? null) ? (int) $filters['ingredient_id'] : null;
        $ingredientCategoryId = filled($filters['ingredient_category_id'] ?? null) ? (int) $filters['ingredient_category_id'] : null;
        $stockStatus = $this->normalizeStockStatus($filters['stock_status'] ?? null);
        $productCategoryId = filled($filters['product_category_id'] ?? null) ? (int) $filters['product_category_id'] : null;
        $productType = $this->normalizeProductType($filters['product_type'] ?? null);
        $station = $this->normalizeStation($filters['station'] ?? null);

        return [
            'timezone' => $period['timezone'],
            'preset' => $period['preset'],
            'start_local' => $period['start_local'],
            'end_local' => $period['end_local'],
            'start_utc' => $period['start_utc'],
            'end_utc' => $period['end_utc'],
            'section' => $section,
            'filters' => [
                'ingredient_id' => $ingredientId,
                'ingredient_category_id' => $ingredientCategoryId,
                'stock_status' => $stockStatus,
                'product_category_id' => $productCategoryId,
                'product_type' => $productType,
                'station' => $station,
            ],
            'overview' => $this->inventoryOverview($period['start_utc'], $period['end_utc']),
            'ingredients' => $this->ingredientAnalytics(
                $period['start_utc'],
                $period['end_utc'],
                $ingredientId,
                $ingredientCategoryId,
                $stockStatus,
            ),
            'top_consumed' => $this->topConsumed($period['start_utc'], $period['end_utc']),
            'products' => $this->productAnalytics(
                $period['start_utc'],
                $period['end_utc'],
                $productCategoryId,
                $productType,
                $station,
            ),
            'categories' => $this->categoryAnalytics($period['start_utc'], $period['end_utc']),
            'stations' => $this->stationVolume($period['start_utc'], $period['end_utc']),
            'refills' => $this->refillAnalytics($period['start_utc'], $period['end_utc']),
            'movements' => $this->movementTrace(
                $period['start_utc'],
                $period['end_utc'],
                $ingredientId,
                $ingredientCategoryId,
                100,
            ),
            'filter_options' => [
                'ingredient_categories' => IngredientCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
                'ingredients' => Ingredient::query()->orderBy('name')->pluck('name', 'id')->all(),
                'product_categories' => ProductCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
                'stock_statuses' => InventoryStockStatus::options(),
                'product_types' => ProductType::options(),
                'stations' => PreparationStation::options(),
            ],
        ];
    }

    public function buildOperatorOverview(): array
    {
        $period = $this->resolvePeriod(['preset' => self::PRESET_TODAY]);

        $low = Ingredient::query()->get()->filter(
            fn (Ingredient $ingredient): bool => $ingredient->stockStatus() === InventoryStockStatus::LowStock,
        );
        $oos = Ingredient::query()->get()->filter(
            fn (Ingredient $ingredient): bool => $ingredient->stockStatus() === InventoryStockStatus::OutOfStock,
        );

        return [
            'timezone' => $period['timezone'],
            'start_local' => $period['start_local'],
            'end_local' => $period['end_local'],
            'low_stock_count' => $low->count(),
            'out_of_stock_count' => $oos->count(),
            'low_stock' => $low->take(10)->values()->map(fn (Ingredient $i): array => [
                'id' => $i->id,
                'name' => $i->name,
                'current_stock' => $this->asQty($i->current_stock),
                'unit' => $i->base_measurement_unit?->value ?? $i->measurement_unit?->value,
                'status' => $i->stockStatus()->value,
            ])->all(),
            'out_of_stock' => $oos->take(10)->values()->map(fn (Ingredient $i): array => [
                'id' => $i->id,
                'name' => $i->name,
                'current_stock' => $this->asQty($i->current_stock),
                'unit' => $i->base_measurement_unit?->value ?? $i->measurement_unit?->value,
                'status' => $i->stockStatus()->value,
            ])->all(),
            'today_consumption' => $this->movementTotalsByType(
                $period['start_utc'],
                $period['end_utc'],
                InventoryTransactionType::SaleConsumption,
            ),
            'today_reversals' => $this->movementTotalsByType(
                $period['start_utc'],
                $period['end_utc'],
                InventoryTransactionType::SaleReversal,
            ),
            'pending_refills' => InventoryRefillRequest::query()
                ->whereIn('status', [InventoryRefillRequestStatus::Pending, InventoryRefillRequestStatus::Approved])
                ->count(),
            'recent_sale_movements' => $this->movementTrace(
                $period['start_utc'],
                $period['end_utc'],
                null,
                null,
                15,
                [InventoryTransactionType::SaleConsumption->value, InventoryTransactionType::SaleReversal->value],
            ),
            'top_consumed' => $this->topConsumed($period['start_utc'], $period['end_utc'], 8),
            'stations' => $this->stationVolume($period['start_utc'], $period['end_utc']),
        ];
    }

    public function exportIngredientMovementsCsv(array $filters = []): StreamedResponse
    {
        $report = $this->buildAdminReport($filters);
        $rows = $this->movementTrace(
            $report['start_utc'],
            $report['end_utc'],
            $report['filters']['ingredient_id'],
            $report['filters']['ingredient_category_id'],
            null,
        );

        $filename = sprintf(
            'ingredient-movements-%s-to-%s.csv',
            $report['start_local']->format('Ymd'),
            $report['end_local']->format('Ymd'),
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'timestamp',
                'ingredient',
                'category',
                'movement_type',
                'quantity',
                'unit',
                'order_reference',
                'product',
                'variant',
                'reversal_of_transaction_id',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['timestamp'],
                    $row['ingredient'],
                    $row['category'],
                    $row['movement_type'],
                    $row['quantity'],
                    $row['unit'],
                    $row['order_reference'],
                    $row['product'],
                    $row['variant'],
                    $row['reversal_of_transaction_id'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportProductSalesCsv(array $filters = []): StreamedResponse
    {
        $report = $this->buildAdminReport($filters);
        $rows = $report['products']['rows'];

        $filename = sprintf(
            'product-sales-%s-to-%s.csv',
            $report['start_local']->format('Ymd'),
            $report['end_local']->format('Ymd'),
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'product',
                'variant',
                'category',
                'product_type',
                'station',
                'units',
                'paid_units',
                'transaction_count',
                'sales_amount',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['product'],
                    $row['variant'],
                    $row['category'],
                    $row['product_type'],
                    $row['station'],
                    $row['units'],
                    $row['paid_units'],
                    $row['transaction_count'],
                    $row['sales_amount'],
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
     * @return array<string, mixed>
     */
    protected function inventoryOverview(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        $ingredients = Ingredient::query()->get();
        $healthy = $ingredients->filter(fn (Ingredient $i): bool => $i->stockStatus() === InventoryStockStatus::InStock)->count();
        $low = $ingredients->filter(fn (Ingredient $i): bool => $i->stockStatus() === InventoryStockStatus::LowStock)->count();
        $oos = $ingredients->filter(fn (Ingredient $i): bool => $i->stockStatus() === InventoryStockStatus::OutOfStock)->count();

        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $periodRows = InventoryTransaction::query()
            ->whereBetween('inventory_transactions.created_at', [$from, $to])
            ->selectRaw('transaction_type, base_measurement_unit, COALESCE(SUM(base_quantity), 0) as qty_sum')
            ->groupBy('transaction_type', 'base_measurement_unit')
            ->get();

        $byUnit = [];
        foreach ($periodRows as $row) {
            $unit = $row->base_measurement_unit instanceof IngredientUnit
                ? $row->base_measurement_unit->value
                : (string) $row->base_measurement_unit;
            $type = $row->transaction_type instanceof InventoryTransactionType
                ? $row->transaction_type->value
                : (string) $row->transaction_type;
            $qty = $this->asQty($row->qty_sum);
            $byUnit[$unit] ??= [
                'unit' => $unit,
                'sale_consumption' => '0.000',
                'sale_reversal' => '0.000',
                'restocked' => '0.000',
                'adjusted' => '0.000',
                'net_movement' => '0.000',
            ];

            if ($type === InventoryTransactionType::SaleConsumption->value) {
                $byUnit[$unit]['sale_consumption'] = $this->addQty($byUnit[$unit]['sale_consumption'], $qty);
                $byUnit[$unit]['net_movement'] = $this->subQty($byUnit[$unit]['net_movement'], $qty);
            } elseif ($type === InventoryTransactionType::SaleReversal->value) {
                $byUnit[$unit]['sale_reversal'] = $this->addQty($byUnit[$unit]['sale_reversal'], $qty);
                $byUnit[$unit]['net_movement'] = $this->addQty($byUnit[$unit]['net_movement'], $qty);
            } elseif (in_array($type, self::RESTOCK_TYPES, true)) {
                $byUnit[$unit]['restocked'] = $this->addQty($byUnit[$unit]['restocked'], $qty);
                $byUnit[$unit]['net_movement'] = $this->addQty($byUnit[$unit]['net_movement'], $qty);
            } elseif (in_array($type, self::ADJUSTMENT_TYPES, true)) {
                $signed = $this->signedAdjustmentQty($type, $qty);
                $byUnit[$unit]['adjusted'] = $this->addQty($byUnit[$unit]['adjusted'], $qty);
                $byUnit[$unit]['net_movement'] = $this->addQty($byUnit[$unit]['net_movement'], $signed);
            }
        }

        return [
            'total_ingredients' => $ingredients->count(),
            'healthy' => $healthy,
            'low_stock' => $low,
            'out_of_stock' => $oos,
            'open_refill_requests' => InventoryRefillRequest::query()
                ->whereIn('status', [InventoryRefillRequestStatus::Pending, InventoryRefillRequestStatus::Approved])
                ->count(),
            'period_by_unit' => array_values($byUnit),
            'note' => 'Period quantities are grouped by canonical base unit. Cross-unit totals are intentionally omitted.',
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    protected function ingredientAnalytics(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        ?int $ingredientId,
        ?int $ingredientCategoryId,
        ?string $stockStatus,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $ingredients = Ingredient::query()
            ->with('category')
            ->when($ingredientId, fn (Builder $q) => $q->whereKey($ingredientId))
            ->when($ingredientCategoryId, fn (Builder $q) => $q->where('ingredient_category_id', $ingredientCategoryId))
            ->orderBy('name')
            ->get();

        $aggregates = InventoryTransaction::query()
            ->whereBetween('inventory_transactions.created_at', [$from, $to])
            ->when($ingredientId, fn (Builder $q) => $q->where('ingredient_id', $ingredientId))
            ->when($ingredientCategoryId, function (Builder $q) use ($ingredientCategoryId): void {
                $q->whereIn('ingredient_id', Ingredient::query()
                    ->where('ingredient_category_id', $ingredientCategoryId)
                    ->select('id'));
            })
            ->get(['ingredient_id', 'transaction_type', 'base_quantity'])
            ->groupBy('ingredient_id')
            ->map(function ($rows): array {
                $consumed = '0.000';
                $reversed = '0.000';
                $restocked = '0.000';
                $adjusted = '0.000';

                foreach ($rows as $row) {
                    $type = $row->transaction_type instanceof InventoryTransactionType
                        ? $row->transaction_type->value
                        : (string) $row->transaction_type;
                    $qty = $this->asQty($row->base_quantity);

                    if ($type === InventoryTransactionType::SaleConsumption->value) {
                        $consumed = $this->addQty($consumed, $qty);
                    } elseif ($type === InventoryTransactionType::SaleReversal->value) {
                        $reversed = $this->addQty($reversed, $qty);
                    } elseif (in_array($type, self::RESTOCK_TYPES, true)) {
                        $restocked = $this->addQty($restocked, $qty);
                    } elseif (in_array($type, self::ADJUSTMENT_TYPES, true)) {
                        $adjusted = $this->addQty($adjusted, $qty);
                    }
                }

                return compact('consumed', 'reversed', 'restocked', 'adjusted');
            });

        $openRefills = InventoryRefillRequest::query()
            ->whereIn('status', [InventoryRefillRequestStatus::Pending, InventoryRefillRequestStatus::Approved])
            ->selectRaw('ingredient_id, COUNT(*) as open_count')
            ->groupBy('ingredient_id')
            ->pluck('open_count', 'ingredient_id');

        $rows = [];

        foreach ($ingredients as $ingredient) {
            $status = $ingredient->stockStatus();

            if ($stockStatus !== null && $status->value !== $stockStatus) {
                continue;
            }

            $agg = $aggregates->get($ingredient->id) ?? [
                'consumed' => '0.000',
                'reversed' => '0.000',
                'restocked' => '0.000',
                'adjusted' => '0.000',
            ];
            $consumed = $agg['consumed'];
            $reversed = $agg['reversed'];
            $restocked = $agg['restocked'];
            $adjusted = $agg['adjusted'];
            $net = $this->subQty($this->addQty($restocked, $reversed), $consumed);
            $unit = $ingredient->base_measurement_unit?->value ?? $ingredient->measurement_unit?->value ?? '';

            $rows[] = [
                'ingredient_id' => $ingredient->id,
                'ingredient' => $ingredient->name,
                'category' => $ingredient->category?->name ?? '—',
                'current_stock' => $this->asQty($ingredient->current_stock),
                'unit' => $unit,
                'consumed' => $consumed,
                'reversed' => $reversed,
                'restocked' => $restocked,
                'adjusted' => $adjusted,
                'net_movement' => $net,
                'minimum_stock' => $this->asQty($ingredient->minimum_stock),
                'stock_status' => $status->value,
                'stock_status_label' => $status === InventoryStockStatus::InStock ? 'Healthy' : $status->label(),
                'open_refill_count' => (int) ($openRefills[$ingredient->id] ?? 0),
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * Rank sale_consumption within each compatible base unit (never cross-unit totals).
     *
     * @return array{by_unit: list<array{unit: string, rows: list<array<string, mixed>>}>, rows: list<array<string, mixed>>}
     */
    protected function topConsumed(CarbonImmutable $startUtc, CarbonImmutable $endUtc, int $limit = 15): array
    {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $rows = InventoryTransaction::query()
            ->where('inventory_transactions.transaction_type', InventoryTransactionType::SaleConsumption)
            ->whereBetween('inventory_transactions.created_at', [$from, $to])
            ->join('ingredients', 'ingredients.id', '=', 'inventory_transactions.ingredient_id')
            ->leftJoin('ingredient_categories', 'ingredient_categories.id', '=', 'ingredients.ingredient_category_id')
            ->selectRaw('ingredients.id as ingredient_id')
            ->selectRaw('ingredients.name as ingredient')
            ->selectRaw('ingredient_categories.name as category')
            ->selectRaw('inventory_transactions.base_measurement_unit as unit')
            ->selectRaw('COALESCE(SUM(inventory_transactions.base_quantity), 0) as consumed')
            ->groupBy('ingredients.id', 'ingredients.name', 'ingredient_categories.name', 'inventory_transactions.base_measurement_unit')
            ->orderByDesc('consumed')
            ->get();

        $mapped = $rows->map(function ($row): array {
            $unit = $row->unit instanceof IngredientUnit ? $row->unit->value : (string) $row->unit;

            return [
                'ingredient_id' => (int) $row->ingredient_id,
                'ingredient' => (string) $row->ingredient,
                'category' => (string) ($row->category ?? '—'),
                'unit' => $unit,
                'consumed' => $this->asQty($row->consumed),
            ];
        });

        $byUnit = $mapped
            ->groupBy('unit')
            ->map(function ($unitRows, string $unit) use ($limit): array {
                return [
                    'unit' => $unit,
                    'rows' => $unitRows->take($limit)->values()->all(),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        // Flat rows retain unit labels; not a cross-unit ranking.
        $flat = $mapped->take($limit)->values()->all();

        return [
            'by_unit' => $byUnit,
            'rows' => $flat,
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    protected function productAnalytics(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        ?int $productCategoryId,
        ?string $productType,
        ?string $station,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $paidRetailIds = Order::query()
            ->whereNull('dining_session_id')
            ->where('payment_status', PaymentStatus::Confirmed)
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
            ->whereBetween('payment_confirmed_at', [$from, $to])
            ->pluck('id');

        $paidDiningOrderIds = Order::query()
            ->whereNotNull('dining_session_id')
            ->whereIn('dining_session_id', function ($query) use ($from, $to): void {
                $query->select('id')
                    ->from('dining_sessions')
                    ->where('payment_status', PaymentStatus::Confirmed->value)
                    ->whereBetween('paid_at', [$from, $to]);
            })
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
            ->pluck('id');

        $paidOrderIds = $paidRetailIds->merge($paidDiningOrderIds)->unique()->values();

        $operationalOrderIds = Order::query()
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
            ->whereBetween('placed_at', [$from, $to])
            ->pluck('id');

        $items = OrderItem::query()
            ->with(['product.category'])
            ->whereIn('order_id', $operationalOrderIds->merge($paidOrderIds)->unique())
            ->when($station, fn (Builder $q) => $q->where('preparation_station', $station))
            ->get();

        $grouped = [];

        foreach ($items as $item) {
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
                (string) ($item->preparation_station?->value ?? ''),
            ]);

            $grouped[$key] ??= [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product' => $item->product_name,
                'variant' => $item->variant_name,
                'category' => $product?->category?->name ?? '—',
                'product_type' => $type !== '' ? $type : '—',
                'station' => $item->preparation_station?->value ?? '—',
                'units' => 0,
                'paid_units' => 0,
                'transaction_count' => [],
                'sales_amount' => '0.00',
            ];

            if ($operationalOrderIds->contains($item->order_id)) {
                $grouped[$key]['units'] += (int) $item->quantity;
            }

            if ($paidOrderIds->contains($item->order_id)) {
                $grouped[$key]['paid_units'] += (int) $item->quantity;
                $grouped[$key]['transaction_count'][$item->order_id] = true;
                $grouped[$key]['sales_amount'] = $this->addMoney(
                    $grouped[$key]['sales_amount'],
                    $this->asMoney($item->line_subtotal),
                );
            }
        }

        $rows = collect($grouped)
            ->map(function (array $row): array {
                $paidUnits = (int) $row['paid_units'];
                $sales = (string) $row['sales_amount'];

                return [
                    'product' => $row['product'],
                    'variant' => $row['variant'],
                    'category' => $row['category'],
                    'product_type' => $row['product_type'],
                    'station' => $row['station'],
                    'units' => (int) $row['units'],
                    'paid_units' => $paidUnits,
                    'transaction_count' => count($row['transaction_count']),
                    'sales_amount' => $sales,
                    'average_realized_value' => $paidUnits > 0
                        ? $this->asMoney(bcdiv($sales, (string) $paidUnits, 4))
                        : '0.00',
                ];
            })
            ->sortByDesc('paid_units')
            ->values()
            ->all();

        return ['rows' => $rows];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, food_units: int, beverage_units: int}
     */
    protected function categoryAnalytics(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        $products = $this->productAnalytics($startUtc, $endUtc, null, null, null)['rows'];
        $byCategory = [];
        $food = 0;
        $beverage = 0;
        $totalSales = '0.00';

        foreach ($products as $row) {
            $key = $row['category'];
            $byCategory[$key] ??= [
                'category' => $key,
                'units' => 0,
                'paid_units' => 0,
                'transaction_count' => 0,
                'sales_amount' => '0.00',
            ];
            $byCategory[$key]['units'] += (int) $row['units'];
            $byCategory[$key]['paid_units'] += (int) $row['paid_units'];
            $byCategory[$key]['transaction_count'] += (int) $row['transaction_count'];
            $byCategory[$key]['sales_amount'] = $this->addMoney($byCategory[$key]['sales_amount'], $row['sales_amount']);
            $totalSales = $this->addMoney($totalSales, $row['sales_amount']);

            if ($row['product_type'] === ProductType::Food->value) {
                $food += (int) $row['units'];
            }
            if ($row['product_type'] === ProductType::Beverage->value) {
                $beverage += (int) $row['units'];
            }
        }

        $rows = collect($byCategory)->map(function (array $row) use ($totalSales): array {
            $share = bccomp($totalSales, '0.00', 2) === 1
                ? $this->asMoney(bcmul(bcdiv($row['sales_amount'], $totalSales, 6), '100', 2))
                : '0.00';

            return [
                ...$row,
                'sales_share_percent' => $share,
            ];
        })->sortByDesc('sales_amount')->values()->all();

        return [
            'rows' => $rows,
            'food_units' => $food,
            'beverage_units' => $beverage,
        ];
    }

    /**
     * @return array{bar_units: int, kitchen_units: int, bar_item_lines: int, kitchen_item_lines: int}
     */
    protected function stationVolume(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $rows = OrderItem::query()
            ->whereHas('order', function (Builder $query) use ($from, $to): void {
                $query->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
                    ->whereBetween('placed_at', [$from, $to]);
            })
            ->selectRaw('preparation_station')
            ->selectRaw('COALESCE(SUM(quantity), 0) as units')
            ->selectRaw('COUNT(*) as item_lines')
            ->groupBy('preparation_station')
            ->get()
            ->keyBy(function ($row): string {
                return $row->preparation_station instanceof PreparationStation
                    ? $row->preparation_station->value
                    : (string) $row->preparation_station;
            });

        return [
            'bar_units' => (int) ($rows[PreparationStation::Bar->value]->units ?? 0),
            'kitchen_units' => (int) ($rows[PreparationStation::Kitchen->value]->units ?? 0),
            'bar_item_lines' => (int) ($rows[PreparationStation::Bar->value]->item_lines ?? 0),
            'kitchen_item_lines' => (int) ($rows[PreparationStation::Kitchen->value]->item_lines ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function refillAnalytics(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        $created = InventoryRefillRequest::query()->whereBetween('inventory_refill_requests.created_at', [$from, $to]);
        $byStatus = (clone $created)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $open = InventoryRefillRequest::query()
            ->whereIn('status', [InventoryRefillRequestStatus::Pending, InventoryRefillRequestStatus::Approved])
            ->count();

        $restockMovements = InventoryTransaction::query()
            ->whereIn('transaction_type', self::RESTOCK_TYPES)
            ->whereBetween('inventory_transactions.created_at', [$from, $to])
            ->count();

        $frequent = InventoryRefillRequest::query()
            ->whereBetween('inventory_refill_requests.created_at', [$from, $to])
            ->join('ingredients', 'ingredients.id', '=', 'inventory_refill_requests.ingredient_id')
            ->selectRaw('ingredients.id as ingredient_id, ingredients.name as ingredient, COUNT(*) as request_count')
            ->groupBy('ingredients.id', 'ingredients.name')
            ->orderByDesc('request_count')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'ingredient_id' => (int) $row->ingredient_id,
                'ingredient' => (string) $row->ingredient,
                'request_count' => (int) $row->request_count,
            ])
            ->all();

        return [
            'created_in_period' => (clone $created)->count(),
            'pending' => (int) ($byStatus[InventoryRefillRequestStatus::Pending->value] ?? 0),
            'approved' => (int) ($byStatus[InventoryRefillRequestStatus::Approved->value] ?? 0),
            'rejected' => (int) ($byStatus[InventoryRefillRequestStatus::Rejected->value] ?? 0),
            'completed' => (int) ($byStatus[InventoryRefillRequestStatus::Completed->value] ?? 0),
            'open_now' => $open,
            'restock_movements' => $restockMovements,
            'frequently_refilled' => $frequent,
        ];
    }

    /**
     * @param  list<string>|null  $types
     * @return list<array<string, mixed>>
     */
    protected function movementTrace(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        ?int $ingredientId,
        ?int $ingredientCategoryId,
        ?int $limit,
        ?array $types = null,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);
        $timezone = $this->cafeAvailability->timezone();

        $query = InventoryTransaction::query()
            ->with(['ingredient.category'])
            ->whereBetween('inventory_transactions.created_at', [$from, $to])
            ->when($ingredientId, fn (Builder $q) => $q->where('ingredient_id', $ingredientId))
            ->when($ingredientCategoryId, function (Builder $q) use ($ingredientCategoryId): void {
                $q->whereIn('ingredient_id', Ingredient::query()
                    ->where('ingredient_category_id', $ingredientCategoryId)
                    ->select('id'));
            })
            ->when($types !== null, fn (Builder $q) => $q->whereIn('transaction_type', $types))
            ->orderByDesc('inventory_transactions.created_at')
            ->orderByDesc('inventory_transactions.id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $transactions = $query->get();

        $orderItemIds = $transactions
            ->filter(fn (InventoryTransaction $txn): bool => $txn->reference_type === OrderInventoryConsumptionService::REFERENCE_TYPE_ORDER_ITEM)
            ->pluck('reference_id')
            ->filter()
            ->unique()
            ->values();

        $orderItems = OrderItem::query()
            ->with('order')
            ->whereIn('id', $orderItemIds)
            ->get()
            ->keyBy('id');

        $consumptionsByTxn = OrderInventoryConsumption::query()
            ->where(function (Builder $query) use ($transactions): void {
                $query->whereIn('inventory_transaction_id', $transactions->pluck('id'))
                    ->orWhereIn('reversal_inventory_transaction_id', $transactions->pluck('id'));
            })
            ->get();

        $reversalOriginal = [];
        foreach ($consumptionsByTxn as $consumption) {
            if ($consumption->reversal_inventory_transaction_id) {
                $reversalOriginal[(int) $consumption->reversal_inventory_transaction_id] = (int) $consumption->inventory_transaction_id;
            }
        }

        $rows = [];

        foreach ($transactions as $txn) {
            $item = null;
            if ($txn->reference_type === OrderInventoryConsumptionService::REFERENCE_TYPE_ORDER_ITEM) {
                $item = $orderItems->get((int) $txn->reference_id);
            }

            $rows[] = [
                'id' => $txn->id,
                'timestamp' => optional($txn->created_at)?->timezone($timezone)?->format('Y-m-d H:i:s') ?? '',
                'ingredient' => $txn->ingredient?->name ?? '—',
                'category' => $txn->ingredient?->category?->name ?? '—',
                'movement_type' => $txn->transaction_type instanceof InventoryTransactionType
                    ? $txn->transaction_type->value
                    : (string) $txn->transaction_type,
                'movement_label' => $txn->transaction_type instanceof InventoryTransactionType
                    ? $txn->transaction_type->label()
                    : (string) $txn->transaction_type,
                'quantity' => $this->asQty($txn->base_quantity),
                'unit' => $txn->base_measurement_unit instanceof IngredientUnit
                    ? $txn->base_measurement_unit->value
                    : (string) $txn->base_measurement_unit,
                'order_reference' => $item?->order?->order_number ?? '',
                'order_id' => $item?->order_id,
                'order_item_id' => $item?->id,
                'product' => $item?->product_name ?? '',
                'variant' => $item?->variant_name ?? '',
                'dining_round' => $item?->order?->dining_round_number,
                'reversal_of_transaction_id' => $reversalOriginal[$txn->id] ?? null,
                'order_url' => $item?->order_id
                    ? route('administrator.orders.show', $item->order_id)
                    : null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{unit: string, quantity: string}>
     */
    protected function movementTotalsByType(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        InventoryTransactionType $type,
    ): array {
        [$from, $to] = $this->utcRange($startUtc, $endUtc);

        return InventoryTransaction::query()
            ->where('transaction_type', $type)
            ->whereBetween('inventory_transactions.created_at', [$from, $to])
            ->selectRaw('base_measurement_unit as unit, COALESCE(SUM(base_quantity), 0) as quantity')
            ->groupBy('base_measurement_unit')
            ->get()
            ->map(function ($row): array {
                $unit = $row->unit instanceof IngredientUnit ? $row->unit->value : (string) $row->unit;

                return [
                    'unit' => $unit,
                    'quantity' => $this->asQty($row->quantity),
                ];
            })
            ->all();
    }

    protected function signedAdjustmentQty(string $type, string $qty): string
    {
        $enum = InventoryTransactionType::tryFrom($type);

        if ($enum?->isIncrease()) {
            return $qty;
        }

        if ($enum?->isDecrease()) {
            return $this->asQty(bcmul($qty, '-1', 3));
        }

        // Absolute adjustments (manual_adjustment / correction / opening): treat as unsigned informational.
        return '0.000';
    }

    protected function normalizeSection(?string $section): string
    {
        $section = $section ?: self::SECTION_OVERVIEW;

        return in_array($section, [
            self::SECTION_OVERVIEW,
            self::SECTION_INGREDIENTS,
            self::SECTION_PRODUCTS,
            self::SECTION_REFILLS,
            self::SECTION_MOVEMENTS,
        ], true) ? $section : self::SECTION_OVERVIEW;
    }

    protected function normalizeStockStatus(?string $status): ?string
    {
        if (! filled($status)) {
            return null;
        }

        return InventoryStockStatus::tryFrom($status)?->value;
    }

    protected function normalizeProductType(?string $type): ?string
    {
        if (! filled($type)) {
            return null;
        }

        return ProductType::tryFrom($type)?->value;
    }

    protected function normalizeStation(?string $station): ?string
    {
        if (! filled($station)) {
            return null;
        }

        return PreparationStation::tryFrom($station)?->value;
    }

    protected function asQty(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 3, '.', '');
    }

    protected function addQty(string $left, string $right): string
    {
        return $this->asQty(bcadd($left, $right, 3));
    }

    protected function subQty(string $left, string $right): string
    {
        return $this->asQty(bcsub($left, $right, 3));
    }

    protected function asMoney(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    protected function addMoney(string $left, string $right): string
    {
        return $this->asMoney(bcadd($left, $right, 2));
    }
}
