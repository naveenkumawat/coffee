@extends('administrator.layouts.default')

@section('page-title', 'Create Recipe')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Recipes', 'url' => route('administrator.recipes.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.recipes.partials.form', [
        'title' => 'Create recipe',
        'action' => route('administrator.recipes.store'),
        'method' => 'POST',
        'submit' => 'Create recipe',
        'variantOptions' => $variantOptions,
        'ingredientOptions' => $ingredientOptions,
        'unitOptions' => $unitOptions,
        'lineRows' => $lineRows,
        'selectedVariantId' => $selectedVariantId,
        'recipe' => $recipe,
        'costing' => null,
    ])
@endsection
