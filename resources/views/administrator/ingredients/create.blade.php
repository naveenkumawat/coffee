@extends('administrator.layouts.default')

@section('page-title', 'Create Ingredient')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredients', 'url' => route('administrator.ingredients.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.ingredients.partials.form', [
        'title' => 'Create ingredient',
        'action' => route('administrator.ingredients.store'),
        'method' => 'POST',
        'submit' => 'Create ingredient',
        'ingredient' => $ingredient,
        'categoryOptions' => $categoryOptions,
        'brandOptions' => $brandOptions,
        'unitOptions' => $unitOptions,
    ])
@endsection
