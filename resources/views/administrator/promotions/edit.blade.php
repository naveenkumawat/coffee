@extends('administrator.layouts.default')

@section('page-title', 'Edit Offer')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Offers & Promotions', 'url' => route('administrator.promotions.index')],
        ['label' => $promotion->name],
    ]" />
@endsection

@section('content')
    @include('administrator.promotions.partials.form', [
        'title' => 'Edit offer',
        'action' => route('administrator.promotions.update', $promotion),
        'method' => 'PUT',
        'submit' => 'Save offer',
        'promotion' => $promotion,
        'usageCount' => $usageCount,
    ])
@endsection
