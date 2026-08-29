@extends('administrator.layouts.default')

@section('page-title', 'Edit Ingredient Category')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredient Categories', 'url' => route('administrator.ingredients.categories.index')],
        ['label' => 'Edit'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'View Category',
            'url' => route('administrator.ingredients.categories.show', $category),
            'variant' => 'dark',
            'icon' => 'ki-eye',
        ],
    ]" />
@endsection

@section('content')
    @include('administrator.ingredients.categories.partials.form', [
        'title' => 'Edit ingredient category',
        'action' => route('administrator.ingredients.categories.update', $category),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'category' => $category,
    ])
@endsection
