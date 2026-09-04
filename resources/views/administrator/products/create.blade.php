@extends('administrator.layouts.default')

@section('page-title', 'Create Product')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Products', 'url' => route('administrator.products.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.products.partials.form', [
        'title' => 'Create product',
        'action' => route('administrator.products.store'),
        'method' => 'POST',
        'submit' => 'Create product',
        'product' => $product,
        'categoryOptions' => $categoryOptions,
        'flavourOptions' => $flavourOptions,
        'tagOptions' => $tagOptions,
        'variantUnitOptions' => $variantUnitOptions,
        'variantRows' => $variantRows,
        'addOnOptions' => $addOnOptions,
        'ingredientOptions' => $ingredientOptions,
        'ingredientUnitOptions' => $ingredientUnitOptions,
        'addOnRows' => $addOnRows,
    ])
@endsection
