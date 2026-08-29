@extends('administrator.layouts.default')

@section('page-title', 'Edit Product')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Products', 'url' => route('administrator.products.index')],
        ['label' => $product->name],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.products.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'View', 'url' => route('administrator.products.show', $product), 'variant' => 'success', 'icon' => 'ki-eye'],
    ]" />
@endsection

@section('content')
    @include('administrator.products.partials.form', [
        'title' => 'Edit product',
        'action' => route('administrator.products.update', $product),
        'method' => 'PUT',
        'submit' => 'Update product',
        'product' => $product,
        'categoryOptions' => $categoryOptions,
        'flavourOptions' => $flavourOptions,
        'variantUnitOptions' => $variantUnitOptions,
        'variantRows' => $variantRows,
    ])
@endsection
