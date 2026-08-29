@extends('administrator.layouts.default')

@section('page-title', 'Edit Ingredient')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredients', 'url' => route('administrator.ingredients.index')],
        ['label' => 'Edit'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'View Ingredient',
            'url' => route('administrator.ingredients.show', $ingredient),
            'variant' => 'dark',
            'icon' => 'ki-eye',
        ],
    ]" />
@endsection

@section('content')
    @include('administrator.ingredients.partials.form', [
        'title' => 'Edit ingredient',
        'action' => route('administrator.ingredients.update', $ingredient),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'ingredient' => $ingredient,
        'categoryOptions' => $categoryOptions,
        'brandOptions' => $brandOptions,
        'unitOptions' => $unitOptions,
    ])
@endsection
