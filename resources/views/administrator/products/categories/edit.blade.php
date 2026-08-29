@extends('administrator.layouts.default')

@section('page-title', 'Edit Product Category')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Product Categories', 'url' => route('administrator.products.categories.index')],
        ['label' => $category->name],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.products.categories.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'View', 'url' => route('administrator.products.categories.show', $category), 'variant' => 'success', 'icon' => 'ki-eye'],
    ]" />
@endsection

@section('content')
    @include('administrator.products.categories.partials.form', [
        'title' => 'Edit product category',
        'action' => route('administrator.products.categories.update', $category),
        'method' => 'PUT',
        'submit' => 'Update category',
        'category' => $category,
    ])
@endsection
