@extends('chef.layouts.default')

@section('page-title', 'Chef Dashboard')

@section('page-description', 'Kitchen preparation queue snapshot.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Chef Panel', 'url' => route('chef.dashboard')],
        ['label' => 'Dashboard'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Kitchen Queue', 'url' => route('chef.preparations.index'), 'variant' => 'default', 'icon' => 'ki-chef'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
        <div class="col-md-3">
            <x-internal.stat-card label="Pending" :value="$pending" icon="ki-time" color="warning" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Accepted" :value="$accepted" icon="ki-check-circle" color="info" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Preparing" :value="$preparing" icon="ki-chef" color="dark" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Ready" :value="$ready" icon="ki-delivery-3" color="success" />
        </div>
    </div>
@endsection
