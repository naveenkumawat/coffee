@extends('barista.layouts.default')

@section('page-title', 'Barista Dashboard')

@section('page-description', 'Bar preparation queue snapshot.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Dashboard'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Bar Queue', 'url' => route('barista.preparations.index'), 'variant' => 'default', 'icon' => 'ki-coffee'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
        <div class="col-md-3">
            <x-internal.stat-card
                label="Cafe Ordering"
                :value="$cafeAvailability->available ? 'OPEN' : 'CLOSED'"
                icon="ki-shop"
                :color="$cafeAvailability->available ? 'success' : 'danger'"
                :description="$cafeAvailability->message"
            />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Pending" :value="$pending" icon="ki-time" color="warning" description="Bar tickets waiting to be accepted." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Preparing" :value="$preparing + $accepted" icon="ki-coffee" color="dark" description="Accepted or actively preparing." />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Ready" :value="$ready" icon="ki-delivery-3" color="success" description="Bar tickets marked ready." />
        </div>
    </div>
@endsection
