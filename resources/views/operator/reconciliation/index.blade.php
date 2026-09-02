@extends('operator.layouts.default')

@section('page-title', 'Today Reconciliation')

@section('page-description', 'Operational payment and channel counts for today — not financial analytics.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Operator Panel', 'url' => route('operator.dashboard')],
        ['label' => 'Reconciliation'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Orders', 'url' => route('operator.orders.index'), 'variant' => 'default', 'icon' => 'ki-delivery-2'],
        ['label' => 'Dining', 'url' => route('operator.dining-sessions.index'), 'variant' => 'dark', 'icon' => 'ki-coffee'],
    ]" />
@endsection

@section('content')
    <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-6 mb-7">
        <i class="ki-outline ki-information-5 fs-2tx text-info me-4"></i>
        <div>
            <div class="fw-bold text-gray-900">Operational only</div>
            <div class="text-muted fs-7">
                Today ({{ $reconciliation['timezone'] }})
                · {{ $reconciliation['start_local']->format('d M Y') }}.
                Cost, margin, and long-range revenue reports are Administrator-only.
            </div>
        </div>
    </div>

    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-md-3">
            <x-internal.stat-card label="Paid Transactions Today" :value="$reconciliation['paid_transactions_today']" icon="ki-check-circle" color="success" description="Confirmed retail orders + dining sessions." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Cash Pending" :value="$reconciliation['cash_pending']" icon="ki-wallet" color="warning" description="Cash not yet received." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Cash Received Today" :value="$reconciliation['cash_received']" icon="ki-dollar" color="success" description="Confirmed cash collections today." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="UPI Awaiting Review" :value="$reconciliation['upi_awaiting_review']" icon="ki-picture" color="info" description="Proofs waiting for confirm/reject." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="UPI Confirmed Today" :value="$reconciliation['upi_confirmed_today']" icon="ki-verify" color="primary" description="Confirmed UPI payments today." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Takeaway Paid" :value="$reconciliation['channel_counts']['takeaway']" icon="ki-cup" color="dark" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Delivery Paid" :value="$reconciliation['channel_counts']['delivery']" icon="ki-delivery-3" color="dark" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Dining Paid" :value="$reconciliation['channel_counts']['dining']" icon="ki-coffee" color="dark" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Orders Needing Action" :value="$reconciliation['orders_needing_action']" icon="ki-notification-bing" color="warning" description="Accept / cash / UPI review." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Dining Needing Action" :value="$reconciliation['dining_needing_action']" icon="ki-bill" color="warning" description="UPI review or cash after bill." />
        </div>
    </div>
@endsection
