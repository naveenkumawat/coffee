@extends('administrator.layouts.default')

@section('page-title', 'Recommendation Performance')

@section('page-description', 'Attributed recommendation exposure → click → cart → purchase metrics (correlation, not causation).')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Recommendation Performance'],
    ]" />
@endsection

@section('content')
    @php
        $summary = $report['summary'];
        $formatRate = static fn (?float $rate): string => $rate === null ? '—' : number_format($rate, 2).'%';
    @endphp

    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.reports.recommendations.index') }}" class="row g-6 align-items-end internal-filter-form">
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
                    <x-internal.button-group :items="[
                        ['label' => 'Apply', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.reports.recommendations.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
                    ]" justify="start" />
                </div>
            </form>
            <div class="text-muted fs-8 mt-4">{{ $report['disclaimer'] }}</div>
        </div>
    </div>

    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-md-3"><x-internal.stat-card label="Impressions" :value="number_format($summary['impressions'])" icon="ki-eye" color="primary" /></div>
        <div class="col-md-3"><x-internal.stat-card label="Clicks" :value="number_format($summary['clicks'])" icon="ki-click" color="info" /></div>
        <div class="col-md-3"><x-internal.stat-card label="CTR" :value="$formatRate($summary['ctr'])" icon="ki-chart-line" color="success" /></div>
        <div class="col-md-3"><x-internal.stat-card label="Attributed revenue" :value="'₹'.$summary['attributed_revenue']" icon="ki-dollar" color="warning" /></div>
    </div>
    <div class="row g-5 g-xl-8 mb-7">
        <div class="col-md-3"><x-internal.stat-card label="Attributed cart adds" :value="number_format($summary['attributed_cart_additions'])" icon="ki-basket" color="primary" /></div>
        <div class="col-md-3"><x-internal.stat-card label="Attributed orders" :value="number_format($summary['attributed_orders'])" icon="ki-shop" color="info" /></div>
        <div class="col-md-3"><x-internal.stat-card label="Attributed units" :value="number_format($summary['attributed_units'])" icon="ki-abstract-26" color="success" /></div>
        <div class="col-md-3"><x-internal.stat-card label="Click → purchase" :value="$formatRate($summary['click_to_purchase_rate'])" icon="ki-arrow-right" color="warning" /></div>
    </div>

    <div class="card card-flush internal-card mb-7">
        <div class="card-header"><div class="card-title"><h3 class="fw-bold">By strategy</h3></div></div>
        <div class="card-body pt-0 table-responsive">
            <table class="table align-middle table-row-dashed fs-7 gy-4">
                <thead>
                    <tr class="text-start text-muted fw-bold text-uppercase gs-0">
                        <th>Strategy</th><th>Impr.</th><th>Clicks</th><th>CTR</th><th>Cart</th><th>Conv.</th><th>Units</th><th>Revenue</th><th>C→P</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['strategies'] as $row)
                        <tr>
                            <td>{{ $row['strategy'] }}</td>
                            <td>{{ number_format($row['impressions']) }}</td>
                            <td>{{ number_format($row['clicks']) }}</td>
                            <td>{{ $formatRate($row['ctr']) }}</td>
                            <td>{{ number_format($row['cart_adds']) }}</td>
                            <td>{{ number_format($row['conversions']) }}</td>
                            <td>{{ number_format($row['units']) }}</td>
                            <td>₹{{ $row['revenue'] }}</td>
                            <td>{{ $formatRate($row['click_to_purchase_rate']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-muted">No recommendation activity in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-5">
        <div class="col-lg-6">
            <div class="card card-flush internal-card mb-7">
                <div class="card-header"><div class="card-title"><h3 class="fw-bold">By placement</h3></div></div>
                <div class="card-body pt-0 table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead><tr class="text-muted fw-bold text-uppercase gs-0"><th>Placement</th><th>Impr.</th><th>Conv.</th><th>Rate</th><th>Revenue</th></tr></thead>
                        <tbody>
                            @forelse ($report['placements'] as $row)
                                <tr>
                                    <td>{{ $row['placement'] }}</td>
                                    <td>{{ number_format($row['impressions']) }}</td>
                                    <td>{{ number_format($row['conversions']) }}</td>
                                    <td>{{ $formatRate($row['conversion_rate']) }}</td>
                                    <td>₹{{ $row['revenue'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No placement data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-flush internal-card mb-7">
                <div class="card-header"><div class="card-title"><h3 class="fw-bold">Top attributed products</h3></div></div>
                <div class="card-body pt-0 table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead><tr class="text-muted fw-bold text-uppercase gs-0"><th>Product</th><th>Conv.</th><th>Units</th><th>Revenue</th></tr></thead>
                        <tbody>
                            @forelse ($report['products'] as $row)
                                <tr>
                                    <td>{{ $row['product_name'] }}</td>
                                    <td>{{ number_format($row['conversions']) }}</td>
                                    <td>{{ number_format($row['units']) }}</td>
                                    <td>₹{{ $row['revenue'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">No attributed purchases yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
