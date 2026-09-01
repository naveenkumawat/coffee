@extends('waiter.layouts.default')

@section('page-title', 'Waiter Dashboard')

@section('page-description', 'Live table and dining session operational snapshot.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Waiter Panel', 'url' => route('waiter.dashboard')],
        ['label' => 'Dashboard'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Tables', 'url' => route('waiter.tables.index'), 'variant' => 'default', 'icon' => 'ki-tablet'],
        ['label' => 'Sessions', 'url' => route('waiter.sessions.index'), 'variant' => 'dark', 'icon' => 'ki-coffee'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
        <div class="col-md-3">
            <x-internal.stat-card label="Available" :value="$available" icon="ki-check-circle" color="success" description="Tables ready to seat." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Occupied" :value="$occupied" icon="ki-people" color="warning" description="Active dining sessions." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Bill Requested" :value="$billRequested" icon="ki-bill" color="info" description="Tables waiting for bill handling." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Awaiting Payment" :value="$awaitingPayment" icon="ki-wallet" color="primary" description="Sessions ready for payment close-out." />
        </div>
    </div>
@endsection
