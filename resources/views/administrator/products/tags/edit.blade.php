@extends('administrator.layouts.default')

@section('page-title', 'Edit Product Tag')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Product Tags', 'url' => route('administrator.products.tags.index')],
        ['label' => $tag->name],
    ]" />
@endsection

@section('content')
    @include('administrator.products.tags.partials.form', [
        'title' => 'Edit product tag',
        'action' => route('administrator.products.tags.update', $tag),
        'method' => 'PUT',
        'submit' => 'Save tag',
        'tag' => $tag,
        'styleOptions' => $styleOptions,
    ])
@endsection
