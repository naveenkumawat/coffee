@extends('administrator.layouts.default')

@section('page-title', 'Edit Recipe')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Recipes', 'url' => route('administrator.recipes.index')],
        ['label' => $recipe->variant?->product?->name.' - '.$recipe->variant?->name],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.recipes.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'View', 'url' => route('administrator.recipes.show', $recipe), 'variant' => 'success', 'icon' => 'ki-eye'],
    ]" />
@endsection

@section('content')
    @include('administrator.recipes.partials.form', [
        'title' => 'Edit recipe',
        'action' => route('administrator.recipes.update', $recipe),
        'method' => 'PUT',
        'submit' => 'Update recipe',
        'variantOptions' => $variantOptions,
        'ingredientOptions' => $ingredientOptions,
        'unitOptions' => $unitOptions,
        'lineRows' => $lineRows,
        'selectedVariantId' => $selectedVariantId,
        'recipe' => $recipe,
        'costing' => $costing,
    ])
@endsection
