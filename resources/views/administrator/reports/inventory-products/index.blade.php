@extends('administrator.layouts.default')

@section('page-title', 'Inventory & Product Analytics')

@section('page-description', 'Ledger-backed inventory movement and snapshot-backed product volume analytics.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Inventory & Product Analytics'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'Export Movements CSV',
            'url' => route('administrator.reports.inventory-products.export.ingredient-movements', request()->query()),
            'variant' => 'dark',
            'icon' => 'ki-file-down',
        ],
        [
            'label' => 'Export Product Sales CSV',
            'url' => route('administrator.reports.inventory-products.export.product-sales', request()->query()),
            'variant' => 'dark',
            'icon' => 'ki-file-down',
        ],
    ]" />
@endsection

@section('content')
    @php
        $overview = $report['overview'];
        $section = $report['section'];
        $options = $report['filter_options'];
        $queryBase = request()->except('section');
    @endphp

    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.reports.inventory-products.index') }}" class="row g-6 align-items-end internal-filter-form">
                <input type="hidden" name="section" value="{{ $section }}" />
                <div class="col-xl-2 col-md-4">
                    <label for="preset" class="form-label">Date range</label>
                    <select id="preset" name="preset" class="form-select">
                        <option value="today" @selected($filters['preset'] === 'today')>Today</option>
                        <option value="yesterday" @selected($filters['preset'] === 'yesterday')>Yesterday</option>
                        <option value="last_7_days" @selected($filters['preset'] === 'last_7_days')>Last 7 days</option>
                        <option value="this_month" @selected($filters['preset'] === 'this_month')>This month</option>
                        <option value="custom" @selected($filters['preset'] === 'custom')>Custom</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="from" class="form-label">From</label>
                    <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="form-control" />
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="to" class="form-label">To</label>
                    <input id="to" name="to" type="date" value="{{ $filters['to'] }}" class="form-control" />
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="ingredient_id" class="form-label">Ingredient</label>
                    <select id="ingredient_id" name="ingredient_id" class="form-select">
                        <option value="">All ingredients</option>
                        @foreach ($options['ingredients'] as $id => $name)
                            <option value="{{ $id }}" @selected((string) $filters['ingredient_id'] === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="ingredient_category_id" class="form-label">Ingredient category</label>
                    <select id="ingredient_category_id" name="ingredient_category_id" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($options['ingredient_categories'] as $id => $name)
                            <option value="{{ $id }}" @selected((string) $filters['ingredient_category_id'] === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="stock_status" class="form-label">Stock status</label>
                    <select id="stock_status" name="stock_status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($options['stock_statuses'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['stock_status'] === $value)>{{ $value === 'in_stock' ? 'Healthy' : $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="product_category_id" class="form-label">Product category</label>
                    <select id="product_category_id" name="product_category_id" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($options['product_categories'] as $id => $name)
                            <option value="{{ $id }}" @selected((string) $filters['product_category_id'] === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="product_type" class="form-label">Product type</label>
                    <select id="product_type" name="product_type" class="form-select">
                        <option value="">All types</option>
                        @foreach ($options['product_types'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['product_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="station" class="form-label">Station</label>
                    <select id="station" name="station" class="form-select">
                        <option value="">All stations</option>
                        @foreach ($options['stations'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['station'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <x-internal.button-group :items="[
                        ['label' => 'Apply', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.reports.inventory-products.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
                    ]" justify="start" />
                </div>
            </form>
            <div class="text-muted fs-8 mt-4">
                Business timezone: {{ $report['timezone'] }}
                · {{ $report['start_local']->format('d M Y H:i') }}
                – {{ $report['end_local']->format('d M Y H:i') }}
            </div>
        </div>
    </div>

    <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-7">
        @foreach ([
            'overview' => 'Overview',
            'ingredients' => 'Ingredients',
            'products' => 'Product Sales',
            'refills' => 'Refills',
            'movements' => 'Movement History',
        ] as $key => $label)
            <li class="nav-item">
                <a class="nav-link text-active-primary ms-0 me-8 py-4 {{ $section === $key ? 'active' : '' }}"
                   href="{{ route('administrator.reports.inventory-products.index', array_merge($queryBase, ['section' => $key])) }}">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    @if ($section === 'overview')
        <div class="row g-5 g-xl-10 mb-7">
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Total Ingredients" :value="$overview['total_ingredients']" icon="ki-flask" color="primary" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Healthy" :value="$overview['healthy']" icon="ki-check-circle" color="success" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Low Stock" :value="$overview['low_stock']" icon="ki-information-5" color="warning" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Out of Stock" :value="$overview['out_of_stock']" icon="ki-cross-circle" color="danger" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Open Refills" :value="$overview['open_refill_requests']" icon="ki-delivery-3" color="info" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="BAR Units" :value="$report['stations']['bar_units']" icon="ki-cup" color="dark" description="Operational volume." /></div>
        </div>

        <div class="row g-5 g-xl-10 mb-7">
            <div class="col-md-6"><x-internal.stat-card label="KITCHEN Units" :value="$report['stations']['kitchen_units']" icon="ki-chef" color="dark" description="Operational volume." /></div>
            <div class="col-md-6"><x-internal.stat-card label="Food / Beverage Units" :value="$report['categories']['food_units'].' / '.$report['categories']['beverage_units']" icon="ki-chart" color="primary" /></div>
        </div>

        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Period movement by unit</h3></div></div>
            <div class="card-body pt-2">
                <p class="text-muted fs-7">{{ $overview['note'] }}</p>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th>Unit</th>
                                <th>Sale consumption</th>
                                <th>Sale reversal</th>
                                <th>Restocked</th>
                                <th>Adjusted</th>
                                <th>Net movement</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($overview['period_by_unit'] as $row)
                                <tr>
                                    <td>{{ $row['unit'] }}</td>
                                    <td>{{ $row['sale_consumption'] }}</td>
                                    <td>{{ $row['sale_reversal'] }}</td>
                                    <td>{{ $row['restocked'] }}</td>
                                    <td>{{ $row['adjusted'] }}</td>
                                    <td>{{ $row['net_movement'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No ledger movements in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Top consumed (ranked within unit)</h3></div></div>
            <div class="card-body pt-2">
                @forelse ($report['top_consumed']['by_unit'] as $unitBlock)
                    <h4 class="fw-semibold fs-6 mt-4">{{ strtoupper($unitBlock['unit']) }}</h4>
                    <div class="table-responsive mb-5">
                        <table class="table align-middle table-row-dashed fs-7 gy-3">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase">
                                    <th>Ingredient</th>
                                    <th>Category</th>
                                    <th>Consumed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($unitBlock['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['ingredient'] }}</td>
                                        <td>{{ $row['category'] }}</td>
                                        <td>{{ $row['consumed'] }} {{ $row['unit'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="text-muted mb-0">No sale consumption in this period.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($section === 'ingredients')
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Ingredient analytics</h3></div></div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th>Ingredient</th>
                                <th>Category</th>
                                <th>Current</th>
                                <th>Consumed</th>
                                <th>Reversed</th>
                                <th>Restocked</th>
                                <th>Adjusted</th>
                                <th>Net</th>
                                <th>Min</th>
                                <th>Status</th>
                                <th>Open refills</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['ingredients']['rows'] as $row)
                                <tr>
                                    <td>{{ $row['ingredient'] }}</td>
                                    <td>{{ $row['category'] }}</td>
                                    <td>{{ $row['current_stock'] }} {{ $row['unit'] }}</td>
                                    <td>{{ $row['consumed'] }}</td>
                                    <td>{{ $row['reversed'] }}</td>
                                    <td>{{ $row['restocked'] }}</td>
                                    <td>{{ $row['adjusted'] }}</td>
                                    <td>{{ $row['net_movement'] }}</td>
                                    <td>{{ $row['minimum_stock'] }}</td>
                                    <td>{{ $row['stock_status_label'] }}</td>
                                    <td>{{ $row['open_refill_count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="text-muted">No ingredients match filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($section === 'products')
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Product / variant sales</h3></div></div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Station</th>
                                <th>Units</th>
                                <th>Paid units</th>
                                <th>Txn count</th>
                                <th>Sales</th>
                                <th>Avg realized</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['products']['rows'] as $row)
                                <tr>
                                    <td>{{ $row['product'] }}</td>
                                    <td>{{ $row['variant'] }}</td>
                                    <td>{{ $row['category'] }}</td>
                                    <td>{{ $row['product_type'] }}</td>
                                    <td>{{ $row['station'] }}</td>
                                    <td>{{ $row['units'] }}</td>
                                    <td>{{ $row['paid_units'] }}</td>
                                    <td>{{ $row['transaction_count'] }}</td>
                                    <td>₹{{ $row['sales_amount'] }}</td>
                                    <td>₹{{ $row['average_realized_value'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-muted">No product sales in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Category share</h3></div></div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th>Category</th>
                                <th>Units</th>
                                <th>Paid units</th>
                                <th>Txn count</th>
                                <th>Sales</th>
                                <th>Share %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['categories']['rows'] as $row)
                                <tr>
                                    <td>{{ $row['category'] }}</td>
                                    <td>{{ $row['units'] }}</td>
                                    <td>{{ $row['paid_units'] }}</td>
                                    <td>{{ $row['transaction_count'] }}</td>
                                    <td>₹{{ $row['sales_amount'] }}</td>
                                    <td>{{ $row['sales_share_percent'] }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No category sales in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($section === 'refills')
        @php $refills = $report['refills']; @endphp
        <div class="row g-5 g-xl-10 mb-7">
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Created in period" :value="$refills['created_in_period']" icon="ki-plus" color="primary" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Pending" :value="$refills['pending']" icon="ki-time" color="warning" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Approved" :value="$refills['approved']" icon="ki-check" color="info" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Rejected" :value="$refills['rejected']" icon="ki-cross" color="danger" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Completed" :value="$refills['completed']" icon="ki-verify" color="success" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Open now" :value="$refills['open_now']" icon="ki-notification-bing" color="dark" /></div>
        </div>
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Frequently refilled</h3></div></div>
            <div class="card-body pt-2">
                <p class="text-muted fs-7">Restock movements in period: {{ $refills['restock_movements'] }}</p>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th>Ingredient</th>
                                <th>Requests</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($refills['frequently_refilled'] as $row)
                                <tr>
                                    <td>{{ $row['ingredient'] }}</td>
                                    <td>{{ $row['request_count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted">No refill requests in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($section === 'movements')
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Movement history</h3></div></div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th>Timestamp</th>
                                <th>Ingredient</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Order</th>
                                <th>Product</th>
                                <th>Round</th>
                                <th>Reversal of</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['movements'] as $row)
                                <tr>
                                    <td>{{ $row['timestamp'] }}</td>
                                    <td>{{ $row['ingredient'] }} <span class="text-muted">({{ $row['category'] }})</span></td>
                                    <td>{{ $row['movement_label'] }}</td>
                                    <td>{{ $row['quantity'] }} {{ $row['unit'] }}</td>
                                    <td>
                                        @if ($row['order_url'])
                                            <a href="{{ $row['order_url'] }}">{{ $row['order_reference'] }}</a>
                                        @else
                                            {{ $row['order_reference'] ?: '—' }}
                                        @endif
                                    </td>
                                    <td>{{ trim($row['product'].' '.$row['variant']) ?: '—' }}</td>
                                    <td>{{ $row['dining_round'] ?? '—' }}</td>
                                    <td>{{ $row['reversal_of_transaction_id'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-muted">No movements in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
