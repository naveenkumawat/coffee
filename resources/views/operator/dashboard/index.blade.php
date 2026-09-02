@extends('operator.layouts.default')

@section('page-title', 'Operator Dashboard')

@section('page-description', 'Live operations snapshot across orders, stations, and dining.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Operator Panel', 'url' => route('operator.dashboard')],
        ['label' => 'Dashboard'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Orders', 'url' => route('operator.orders.index'), 'variant' => 'default', 'icon' => 'ki-delivery-2'],
        ['label' => 'Preparation', 'url' => route('operator.preparations.index'), 'variant' => 'dark', 'icon' => 'ki-chef'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
        <div class="col-md-3">
            <x-internal.stat-card label="New (Payment Confirmed)" :value="$newOrders" icon="ki-wallet" color="warning" description="Awaiting operator accept." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Accepted" :value="$acceptedOrders" icon="ki-check-circle" color="info" description="Handed to stations." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Preparing" :value="$preparingOrders" icon="ki-chef" color="dark" description="Overall order preparing." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Ready" :value="$readyOrders" icon="ki-delivery-3" color="success" description="Ready for pickup / handoff." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Bar Queue Active" :value="$barQueueActive" icon="ki-coffee" color="primary" description="Pending + accepted + preparing bar tickets." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Kitchen Queue Active" :value="$kitchenQueueActive" icon="ki-cup" color="primary" description="Pending + accepted + preparing kitchen tickets." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Dining Active" :value="$diningActive" icon="ki-people" color="warning" description="Occupied / bill / payment tables." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Bill Requested" :value="$billRequested" icon="ki-bill" color="info" description="Tables waiting for bill handling." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Paid Today" :value="$reconciliation['paid_transactions_today']" icon="ki-check-circle" color="success" description="Confirmed retail + dining today." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Cash Pending" :value="$reconciliation['cash_pending']" icon="ki-wallet" color="warning" description="Cash awaiting receipt." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="UPI Awaiting" :value="$reconciliation['upi_awaiting_review']" icon="ki-picture" color="info" description="Proofs awaiting review." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Needs Action" :value="$reconciliation['orders_needing_action'] + $reconciliation['dining_needing_action']" icon="ki-notification-bing" color="danger" description="Orders/sessions needing operator action." />
        </div>
    </div>

    <div class="mb-5">
        <a href="{{ route('operator.reconciliation.index') }}" class="btn btn-sm btn-light-primary">Open today reconciliation</a>
    </div>
@endsection
