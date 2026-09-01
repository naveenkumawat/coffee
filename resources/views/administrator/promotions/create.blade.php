@extends('administrator.layouts.default')

@section('page-title', 'Create Offer')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Offers & Promotions', 'url' => route('administrator.promotions.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.promotions.partials.form', [
        'title' => 'Create offer',
        'action' => route('administrator.promotions.store'),
        'method' => 'POST',
        'submit' => 'Create offer',
        'promotion' => $promotion,
        'usageCount' => null,
    ])
@endsection
