@extends('administrator.layouts.default')

@section('page-title', 'Create Ingredient Category')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredient Categories', 'url' => route('administrator.ingredients.categories.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.ingredients.categories.partials.form', [
        'title' => 'Create ingredient category',
        'action' => route('administrator.ingredients.categories.store'),
        'method' => 'POST',
        'submit' => 'Create category',
        'category' => $category,
    ])
@endsection
