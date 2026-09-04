@extends('administrator.layouts.default')

@section('page-title', 'Loyalty Ledger')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Loyalty Operations', 'url' => route('administrator.loyalty-operations.index')],
        ['label' => 'Ledger'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'Export CSV',
            'url' => route('administrator.loyalty-operations.export.ledger', request()->query()),
            'variant' => 'dark',
            'icon' => 'ki-file-down',
        ],
        [
            'label' => 'Back to dashboard',
            'url' => route('administrator.loyalty-operations.index', request()->query()),
            'variant' => 'primary',
            'icon' => 'ki-left',
        ],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.loyalty-operations.ledger') }}" class="row g-6 align-items-end internal-filter-form">
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
                    <label for="transaction_type" class="form-label">Type</label>
                    <select id="transaction_type" name="transaction_type" class="form-select">
                        <option value="all" @selected($filters['transaction_type'] === 'all')>All</option>
                        <option value="earn" @selected($filters['transaction_type'] === 'earn')>Earn</option>
                        <option value="redeem" @selected($filters['transaction_type'] === 'redeem')>Redeem</option>
                        <option value="adjustment" @selected($filters['transaction_type'] === 'adjustment')>Adjustment</option>
                        <option value="restore" @selected($filters['transaction_type'] === 'restore')>Restore</option>
                        <option value="earn_reversal" @selected($filters['transaction_type'] === 'earn_reversal')>Earn reversal</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="reward_id" class="form-label">Reward</label>
                    <select id="reward_id" name="reward_id" class="form-select">
                        <option value="">All rewards</option>
                        @foreach ($rewardOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['reward_id'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="q" class="form-label">Customer</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" class="form-control" placeholder="Name or email" />
                </div>
                <div class="col-xl-2 col-md-4">
                    <x-internal.button-group :items="[
                        ['label' => 'Apply', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                    ]" justify="start" />
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-7 gy-4 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold text-uppercase gs-0">
                            <th>When</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Points</th>
                            <th>Reason</th>
                            <th>Order</th>
                            <th>Txn</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $txn)
                            @php $metadata = is_array($txn->metadata) ? $txn->metadata : []; @endphp
                            <tr>
                                <td>{{ $txn->occurred_at?->format('d M Y, H:i') }}</td>
                                <td>
                                    @if ($txn->customer)
                                        <a href="{{ route('administrator.users.show', $txn->customer) }}" class="text-gray-900 fw-bold">{{ $txn->customer->name }}</a>
                                    @else
                                        #{{ $txn->customer_id }}
                                    @endif
                                </td>
                                <td>{{ $txn->type?->label() ?? $txn->type }}</td>
                                <td>{{ $txn->points > 0 ? '+'.$txn->points : $txn->points }}</td>
                                <td>{{ $txn->description ?: ($txn->reason_code ?: '—') }}</td>
                                <td>{{ $metadata['order_number'] ?? '—' }}</td>
                                <td>#{{ $txn->id }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-10">No ledger rows for these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $transactions->links('components.internal.pagination') }}</div>
        </div>
    </div>
@endsection
