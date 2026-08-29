@extends('administrator.layouts.default')

@section('page-title', 'Create Product Flavour')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Product Flavours', 'url' => route('administrator.products.flavours.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.products.flavours.partials.form', [
        'title' => 'Create product flavour',
        'action' => route('administrator.products.flavours.store'),
        'method' => 'POST',
        'submit' => 'Create flavour',
        'flavour' => $flavour,
        'categoryOptions' => $categoryOptions,
    ])
@endsection
