@extends('administrator.layouts.default')

@section('page-title', 'Create Ingredient Brand')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredient Brands', 'url' => route('administrator.ingredients.brands.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.ingredients.brands.partials.form', [
        'title' => 'Create ingredient brand',
        'action' => route('administrator.ingredients.brands.store'),
        'method' => 'POST',
        'submit' => 'Create brand',
        'brand' => $brand,
    ])
@endsection
