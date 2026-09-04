@extends('administrator.layouts.default')

@section('page-title', 'Loyalty Operations')

@section('page-description', 'Earn, redeem, adjustment, outstanding points, and debt operational reporting. Outstanding points are not a currency liability.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Loyalty Operations'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'Ledger export',
            'url' => route('administrator.loyalty-operations.export.ledger', request()->query()),
            'variant' => 'dark',
            'icon' => 'ki-file-down',
        ],
        [
            'label' => 'Balances export',
            'url' => route('administrator.loyalty-operations.export.balances', request()->query()),
            'variant' => 'dark',
            'icon' => 'ki-file-down',
        ],
        [
            'label' => 'Redemptions export',
            'url' => route('administrator.loyalty-operations.export.redemptions', request()->query()),
            'variant' => 'dark',
            'icon' => 'ki-file-down',
        ],
        [
            'label' => 'Ledger',
            'url' => route('administrator.loyalty-operations.ledger', request()->query()),
            'variant' => 'primary',
            'icon' => 'ki-tablet',
        ],
        [
            'label' => 'Adjustments',
            'url' => route('administrator.loyalty-operations.adjustments', request()->query()),
            'variant' => 'primary',
            'icon' => 'ki-pencil',
        ],
    ]" />
@endsection

@section('content')
    @php
        $summary = $report['summary'];
        $metric = static function ($value) {
            return $value === null ? '—' : $value;
        };
    @endphp

    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.loyalty-operations.index') }}" class="row g-6 align-items-end internal-filter-form">
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
                        ['label' => 'Reset', 'url' => route('administrator.loyalty-operations.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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

    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Active accounts" :value="$summary['active_accounts']" icon="ki-profile-user" color="primary" description="Loyalty accounts created." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Points earned" :value="$summary['earned_points']" icon="ki-plus-square" color="success" description="Canonical earn ledger in range." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Points redeemed" :value="$summary['redeemed_points']" icon="ki-gift" color="warning" description="Absolute redeem ledger in range." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Restored / reversed" :value="$summary['restored_points'].' / '.$summary['reversed_earn_points']" icon="ki-arrows-circle" color="info" description="Restore + earn reversal magnitudes." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Manual adjustments" :value="$summary['adjustment_count']" icon="ki-pencil" color="dark" description="Net {{ $summary['adjustment_net_points'] }} pts (+{{ $summary['adjustment_positive_points'] }} / −{{ $summary['adjustment_negative_points'] }})." />
        </div>
        <div class="col-md-4 col-xl-2">
            <x-internal.stat-card label="Redemption rate" :value="$metric($summary['redemption_rate_percent'] !== null ? $summary['redemption_rate_percent'].'%' : null)" icon="ki-chart-line" color="primary" description="Redemptions ÷ qualifying earn orders." />
        </div>
    </div>

    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-md-4 col-xl-3">
            <x-internal.stat-card label="Outstanding points" :value="$summary['outstanding_points']" icon="ki-abstract-26" color="success" description="Sum of positive balances (not cash liability)." />
        </div>
        <div class="col-md-4 col-xl-3">
            <x-internal.stat-card label="Positive balance customers" :value="$summary['positive_balance_customers']" icon="ki-people" color="primary" description="Avg positive {{ $metric($summary['average_positive_balance']) }}." />
        </div>
        <div class="col-md-4 col-xl-3">
            <x-internal.stat-card label="Debt customers" :value="$summary['debt_customers']" icon="ki-information-2" color="danger" description="Customers with negative available points." />
        </div>
        <div class="col-md-4 col-xl-3">
            <x-internal.stat-card label="Debt points" :value="$summary['debt_points']" icon="ki-minus-square" color="warning" description="Absolute magnitude of negative balances." />
        </div>
    </div>

    <div class="row g-5 mb-7">
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Earn / redeem breakdown</h3></div></div>
                <div class="card-body pt-2">
                    <div class="d-flex justify-content-between mb-3"><span class="text-muted">Earning customers</span><span class="fw-bold">{{ $summary['earning_customers'] }}</span></div>
                    <div class="d-flex justify-content-between mb-3"><span class="text-muted">Qualifying orders</span><span class="fw-bold">{{ $summary['qualifying_orders'] }}</span></div>
                    <div class="d-flex justify-content-between mb-3"><span class="text-muted">Avg earned / order</span><span class="fw-bold">{{ $metric($summary['average_earned_per_order']) }}</span></div>
                    <div class="d-flex justify-content-between mb-3"><span class="text-muted">Redemption count</span><span class="fw-bold">{{ $summary['redemption_count'] }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Redeeming customers</span><span class="fw-bold">{{ $summary['redeeming_customers'] }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Top redeemed rewards</h3></div></div>
                <div class="card-body pt-2">
                    @forelse ($report['top_redeemed_rewards'] as $row)
                        <div class="d-flex justify-content-between mb-3">
                            <span>{{ $row['name'] }}</span>
                            <span class="fw-bold">{{ $row['redemption_count'] }} · {{ $row['points_consumed'] }} pts</span>
                        </div>
                    @empty
                        <div class="text-muted">No redemptions in this range.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card mb-7">
        <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Reward performance</h3></div></div>
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-7 gy-4 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold text-uppercase gs-0">
                            <th>Reward</th>
                            <th>Views</th>
                            <th>Selections</th>
                            <th>Redeems</th>
                            <th>Points</th>
                            <th>Discount</th>
                            <th>Customers</th>
                            <th>View→Select</th>
                            <th>Select→Redeem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['reward_performance'] as $row)
                            <tr>
                                <td class="fw-bold text-gray-900">{{ $row['name'] }}</td>
                                <td>{{ $row['views'] }}</td>
                                <td>{{ $row['selections'] }}</td>
                                <td>{{ $row['redemptions'] }}</td>
                                <td>{{ $row['points_consumed'] }}</td>
                                <td>₹{{ $row['discount_value'] }}</td>
                                <td>{{ $row['unique_customers'] }}</td>
                                <td>{{ $metric($row['view_to_select_percent'] !== null ? $row['view_to_select_percent'].'%' : null) }}</td>
                                <td>{{ $metric($row['select_to_redeem_percent'] !== null ? $row['select_to_redeem_percent'].'%' : null) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-8">No performance evidence in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-header pt-6"><div class="card-title"><h3 class="fw-bold">Reporting definitions</h3></div></div>
        <div class="card-body pt-2">
            <ul class="text-muted fs-7 mb-0">
                @foreach ($report['definitions'] as $key => $definition)
                    <li class="mb-2"><span class="fw-bold text-gray-800">{{ $key }}</span> — {{ $definition }}</li>
                @endforeach
            </ul>
            <div class="text-muted fs-8 mt-4">
                Customer lookup stays on Users. Compensating adjustments only — adjustments are never edited or deleted.
            </div>
        </div>
    </div>
@endsection
