@extends('administrator.layouts.default')

@section('page-title', 'Create Product Tag')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Product Tags', 'url' => route('administrator.products.tags.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.products.tags.partials.form', [
        'title' => 'Create product tag',
        'action' => route('administrator.products.tags.store'),
        'method' => 'POST',
        'submit' => 'Create tag',
        'tag' => $tag,
        'styleOptions' => $styleOptions,
    ])
@endsection
