@extends('administrator.layouts.default')

@section('page-title', 'Loyalty Adjustments')

@section('page-description', 'Immutable adjustment audit. Corrections require a compensating adjustment.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Loyalty Operations', 'url' => route('administrator.loyalty-operations.index')],
        ['label' => 'Adjustments'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
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
            <form method="GET" action="{{ route('administrator.loyalty-operations.adjustments') }}" class="row g-6 align-items-end internal-filter-form">
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
                            <th>Points</th>
                            <th>Reason</th>
                            <th>Administrator</th>
                            <th>Balance after*</th>
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
                                <td>{{ $txn->points > 0 ? '+'.$txn->points : $txn->points }}</td>
                                <td>{{ $txn->description ?: '—' }}</td>
                                <td>{{ $metadata['actor_name'] ?? ('#'.($metadata['actor_id'] ?? '—')) }}</td>
                                <td class="text-muted">—</td>
                                <td>#{{ $txn->id }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-10">No adjustments in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="text-muted fs-8 mt-4">* Running balance after each adjustment is not stored on the ledger row; inspect the customer loyalty account for current balance. Adjustments cannot be edited or deleted.</div>
            <div class="mt-6">{{ $transactions->links('components.internal.pagination') }}</div>
        </div>
    </div>
@endsection
