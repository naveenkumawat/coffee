@extends('administrator.layouts.default')

@section('page-title', 'Create Product Category')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Product Categories', 'url' => route('administrator.products.categories.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.products.categories.partials.form', [
        'title' => 'Create product category',
        'action' => route('administrator.products.categories.store'),
        'method' => 'POST',
        'submit' => 'Create category',
        'category' => $category,
    ])
@endsection
