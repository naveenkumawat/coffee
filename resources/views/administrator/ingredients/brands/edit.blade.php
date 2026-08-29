@extends('administrator.layouts.default')

@section('page-title', 'Edit Ingredient Brand')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredient Brands', 'url' => route('administrator.ingredients.brands.index')],
        ['label' => 'Edit'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'View Brand', 'url' => route('administrator.ingredients.brands.show', $brand), 'variant' => 'dark', 'icon' => 'ki-eye'],
    ]" />
@endsection

@section('content')
    @include('administrator.ingredients.brands.partials.form', [
        'title' => 'Edit ingredient brand',
        'action' => route('administrator.ingredients.brands.update', $brand),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'brand' => $brand,
    ])
@endsection
