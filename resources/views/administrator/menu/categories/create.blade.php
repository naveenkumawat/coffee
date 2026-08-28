@extends('administrator.layouts.default')

@section('page-title', 'Create Menu Category')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Menu Categories', 'url' => route('administrator.menu.categories.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.menu.categories.partials.form', [
        'title' => 'Create menu category',
        'action' => route('administrator.menu.categories.store'),
        'method' => 'POST',
        'submit' => 'Create category',
        'category' => $category,
    ])
@endsection
