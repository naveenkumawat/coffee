@extends('administrator.layouts.default')

@section('page-title', 'Edit Product Flavour')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Product Flavours', 'url' => route('administrator.products.flavours.index')],
        ['label' => $flavour->name],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.products.flavours.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'View', 'url' => route('administrator.products.flavours.show', $flavour), 'variant' => 'success', 'icon' => 'ki-eye'],
    ]" />
@endsection

@section('content')
    @include('administrator.products.flavours.partials.form', [
        'title' => 'Edit product flavour',
        'action' => route('administrator.products.flavours.update', $flavour),
        'method' => 'PUT',
        'submit' => 'Update flavour',
        'flavour' => $flavour,
        'categoryOptions' => $categoryOptions,
    ])
@endsection
