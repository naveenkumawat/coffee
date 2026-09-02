@extends('administrator.layouts.default')

@section('page-title', 'Operational Performance')

@section('page-description', 'BAR/KITCHEN preparation timing, mixed-order coordination, and dining operational metrics from persisted timestamps.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Operational Performance'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'Export Preparations CSV',
            'url' => route('administrator.reports.operational-performance.export.preparations', request()->query()),
            'variant' => 'dark',
            'icon' => 'ki-file-down',
        ],
        [
            'label' => 'Export Dining CSV',
            'url' => route('administrator.reports.operational-performance.export.dining', request()->query()),
            'variant' => 'dark',
            'icon' => 'ki-file-down',
        ],
    ]" />
@endsection

@section('content')
    @php
        $section = $report['section'];
        $options = $report['filter_options'];
        $queryBase = request()->except('section');
        $fmt = function (?int $seconds): string {
            if ($seconds === null) {
                return '—';
            }
            $seconds = abs($seconds);
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;

            return $m > 0 ? sprintf('%dm %02ds', $m, $s) : sprintf('%ds', $s);
        };
        $bar = $report['stations']['bar'];
        $kitchen = $report['stations']['kitchen'];
    @endphp

    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.reports.operational-performance.index') }}" class="row g-6 align-items-end internal-filter-form">
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
                    <label for="station" class="form-label">Station</label>
                    <select id="station" name="station" class="form-select">
                        <option value="">All stations</option>
                        @foreach ($options['stations'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['station'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="channel" class="form-label">Fulfilment</label>
                    <select id="channel" name="channel" class="form-select">
                        @foreach ($options['channels'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['channel'] === $value)>{{ $label }}</option>
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
                    <x-internal.button-group :items="[
                        ['label' => 'Apply', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.reports.operational-performance.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
            'bar' => 'BAR',
            'kitchen' => 'KITCHEN',
            'mixed' => 'Mixed Orders',
            'dining' => 'Dining',
            'long_running' => 'Long Running Tickets',
            'products' => 'Products',
        ] as $key => $label)
            <li class="nav-item">
                <a class="nav-link text-active-primary ms-0 me-8 py-4 {{ $section === $key ? 'active' : '' }}"
                   href="{{ route('administrator.reports.operational-performance.index', array_merge($queryBase, ['section' => $key])) }}">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    @if ($section === 'overview')
        <div class="row g-5 g-xl-10 mb-7">
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Tickets Created" :value="$report['overview']['tickets_created']" icon="ki-abstract-26" color="primary" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Ready Tickets" :value="$report['overview']['ready_tickets']" icon="ki-check-circle" color="success" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Cancelled Tickets" :value="$report['overview']['cancelled_tickets']" icon="ki-cross-circle" color="danger" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Avg Total Ticket" :value="$fmt($report['overview']['avg_total_ticket_seconds'])" icon="ki-timer" color="info" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Live Pending" :value="$report['overview']['active_pending']" icon="ki-time" color="warning" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Live Preparing" :value="$report['overview']['active_preparing']" icon="ki-chef" color="dark" /></div>
        </div>
        <div class="row g-5 mb-7">
            <div class="col-lg-6">
                <div class="card card-flush internal-card h-100">
                    <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">BAR summary</h3></div></div>
                    <div class="card-body pt-2 fs-7">
                        <div>Tickets: <strong>{{ $bar['tickets'] }}</strong> · Ready: <strong>{{ $bar['ready_tickets'] }}</strong></div>
                        <div>Avg queue wait: <strong>{{ $fmt($bar['avg_queue_wait_seconds']) }}</strong></div>
                        <div>Avg prep: <strong>{{ $fmt($bar['avg_preparation_seconds']) }}</strong></div>
                        <div>Avg total: <strong>{{ $fmt($bar['avg_total_ticket_seconds']) }}</strong></div>
                        <div>Max total: <strong>{{ $fmt($bar['max_ticket_time_seconds']) }}</strong></div>
                        <div>Live pending/preparing: <strong>{{ $bar['currently_pending'] }} / {{ $bar['currently_preparing'] }}</strong></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card card-flush internal-card h-100">
                    <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">KITCHEN summary</h3></div></div>
                    <div class="card-body pt-2 fs-7">
                        <div>Tickets: <strong>{{ $kitchen['tickets'] }}</strong> · Ready: <strong>{{ $kitchen['ready_tickets'] }}</strong></div>
                        <div>Avg queue wait: <strong>{{ $fmt($kitchen['avg_queue_wait_seconds']) }}</strong></div>
                        <div>Avg prep: <strong>{{ $fmt($kitchen['avg_preparation_seconds']) }}</strong></div>
                        <div>Avg total: <strong>{{ $fmt($kitchen['avg_total_ticket_seconds']) }}</strong></div>
                        <div>Max total: <strong>{{ $fmt($kitchen['max_ticket_time_seconds']) }}</strong></div>
                        <div>Live pending/preparing: <strong>{{ $kitchen['currently_pending'] }} / {{ $kitchen['currently_preparing'] }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Cancellations (period tickets)</h3></div></div>
            <div class="card-body pt-2">
                Before preparation: <strong>{{ $report['cancellations']['before_preparation'] }}</strong>
                · After preparation began: <strong>{{ $report['cancellations']['after_preparation_began'] }}</strong>
            </div>
        </div>
    @endif

    @if (in_array($section, ['bar', 'kitchen'], true))
        @php $summary = $report['stations'][$section]; @endphp
        <div class="row g-5 g-xl-10 mb-7">
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Tickets" :value="$summary['tickets']" icon="ki-abstract-26" color="primary" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Ready" :value="$summary['ready_tickets']" icon="ki-check" color="success" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Avg Queue Wait" :value="$fmt($summary['avg_queue_wait_seconds'])" icon="ki-time" color="warning" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Avg Prep" :value="$fmt($summary['avg_preparation_seconds'])" icon="ki-timer" color="info" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Avg Total" :value="$fmt($summary['avg_total_ticket_seconds'])" icon="ki-chart" color="dark" /></div>
            <div class="col-md-4 col-xl-2"><x-internal.stat-card label="Max Total" :value="$fmt($summary['max_ticket_time_seconds'])" icon="ki-arrow-up" color="danger" /></div>
        </div>
        <p class="text-muted fs-7">Live pending {{ $summary['currently_pending'] }} · preparing {{ $summary['currently_preparing'] }}. Missing timestamps are excluded from averages (not treated as 0).</p>
    @endif

    @if ($section === 'mixed')
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Mixed BAR + KITCHEN orders</h3></div></div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th>Order</th>
                                <th>Channel</th>
                                <th>BAR ready</th>
                                <th>KITCHEN ready</th>
                                <th>Overall ready</th>
                                <th>Gap</th>
                                <th>Blocking</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['mixed_orders']['rows'] as $row)
                                <tr>
                                    <td>{{ $row['order_number'] }}@if($row['dining_round']) · R{{ $row['dining_round'] }}@endif</td>
                                    <td>{{ $row['channel'] }}</td>
                                    <td>{{ $row['bar_ready_at'] ?: '—' }}</td>
                                    <td>{{ $row['kitchen_ready_at'] ?: '—' }}</td>
                                    <td>{{ $row['all_stations_ready'] ? ($row['overall_ready_at'] ?: '—') : 'Not complete' }}</td>
                                    <td>{{ $fmt($row['station_gap_seconds']) }}</td>
                                    <td>{{ $row['blocking_station'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted">No mixed-station orders in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Retail turnaround averages</h3></div></div>
            <div class="card-body pt-2 fs-7">
                Order→Accept: <strong>{{ $fmt($report['retail_turnaround']['averages']['order_to_accept_seconds']) }}</strong>
                · Accept→Ready: <strong>{{ $fmt($report['retail_turnaround']['averages']['accept_to_ready_seconds']) }}</strong>
                · Total: <strong>{{ $fmt($report['retail_turnaround']['averages']['total_turnaround_seconds']) }}</strong>
                · Ready→Completed: <strong>{{ $fmt($report['retail_turnaround']['averages']['ready_to_completed_seconds']) }}</strong>
            </div>
        </div>
    @endif

    @if ($section === 'dining')
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Dining rounds</h3></div></div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th>Round</th>
                                <th>Round→Ready</th>
                                <th>BAR prep</th>
                                <th>KITCHEN prep</th>
                                <th>Station gap</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['dining_rounds']['rows'] as $row)
                                <tr>
                                    <td>{{ $row['order_number'] }} · R{{ $row['round_number'] }}</td>
                                    <td>{{ $fmt($row['round_to_ready_seconds']) }}</td>
                                    <td>{{ $fmt($row['bar_prep_seconds']) }}</td>
                                    <td>{{ $fmt($row['kitchen_prep_seconds']) }}</td>
                                    <td>{{ $fmt($row['station_gap_seconds']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No dining rounds in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Dining sessions</h3></div></div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase">
                                <th>Session</th>
                                <th>Table</th>
                                <th>Rounds</th>
                                <th>Duration</th>
                                <th>Bill→Pay</th>
                                <th>Pay→Close</th>
                                <th>Occupancy</th>
                                <th>Avg round interval</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['dining_sessions']['rows'] as $row)
                                <tr>
                                    <td>{{ $row['session_number'] }}</td>
                                    <td>{{ $row['table'] }}</td>
                                    <td>{{ $row['round_count'] }}</td>
                                    <td>{{ $fmt($row['session_duration_seconds']) }}</td>
                                    <td>{{ $fmt($row['bill_request_to_payment_seconds']) }}</td>
                                    <td>{{ $fmt($row['payment_to_close_seconds']) }}</td>
                                    <td>{{ $fmt($row['occupancy_seconds']) }}</td>
                                    <td>{{ $fmt($row['avg_round_interval_seconds']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-muted">No dining sessions in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($section === 'long_running')
        @php $live = $report['long_running']; @endphp
        <div class="row g-5 mb-7">
            <div class="col-md-3"><x-internal.stat-card label="BAR Pending" :value="$live['station_backlog']['bar_pending']" icon="ki-cup" color="warning" /></div>
            <div class="col-md-3"><x-internal.stat-card label="BAR Preparing" :value="$live['station_backlog']['bar_preparing']" icon="ki-cup" color="dark" /></div>
            <div class="col-md-3"><x-internal.stat-card label="KITCHEN Pending" :value="$live['station_backlog']['kitchen_pending']" icon="ki-chef" color="warning" /></div>
            <div class="col-md-3"><x-internal.stat-card label="KITCHEN Preparing" :value="$live['station_backlog']['kitchen_preparing']" icon="ki-chef" color="dark" /></div>
        </div>
        <div class="row g-5 mb-7">
            <div class="col-lg-6">
                <div class="card card-flush internal-card h-100">
                    <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Longest pending</h3></div></div>
                    <div class="card-body pt-2">
                        @forelse ($live['longest_pending'] as $row)
                            <div class="d-flex justify-content-between py-2 border-bottom border-gray-100 fs-7">
                                <span>{{ $row['order_number'] }} · {{ strtoupper($row['station']) }}</span>
                                <span class="fw-bold">{{ $fmt($row['queue_age_seconds']) }}</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No pending tickets.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card card-flush internal-card h-100">
                    <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Longest preparing</h3></div></div>
                    <div class="card-body pt-2">
                        @forelse ($live['longest_preparing'] as $row)
                            <div class="d-flex justify-content-between py-2 border-bottom border-gray-100 fs-7">
                                <span>{{ $row['order_number'] }} · {{ strtoupper($row['station']) }} · {{ $row['status'] }}</span>
                                <span class="fw-bold">{{ $fmt($row['stage_elapsed_seconds']) }}</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No preparing tickets.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Mixed orders waiting on another station</h3></div></div>
            <div class="card-body pt-2">
                @forelse ($live['mixed_waiting_on_other_station'] as $row)
                    <div class="fs-7 py-2 border-bottom border-gray-100">
                        {{ $row['order_number'] }} · ready: {{ implode(', ', $row['ready_stations']) ?: '—' }}
                        · waiting: {{ implode(', ', $row['waiting_stations']) ?: '—' }}
                    </div>
                @empty
                    <p class="text-muted mb-0">None.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($section === 'products')
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Preparation by product / variant</h3></div></div>
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
                                <th>Samples</th>
                                <th>Avg prep</th>
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
                                    <td>{{ $row['ready_ticket_samples'] }}</td>
                                    <td>{{ $fmt($row['avg_prep_seconds']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted">No ready-ticket prep samples in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
