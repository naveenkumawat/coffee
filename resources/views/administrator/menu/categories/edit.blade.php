@extends('administrator.layouts.default')

@section('page-title', 'Edit Menu Category')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Menu Categories', 'url' => route('administrator.menu.categories.index')],
        ['label' => 'Edit'],
    ]" />
@endsection

@section('content')
    @include('administrator.menu.categories.partials.form', [
        'title' => 'Edit menu category',
        'action' => route('administrator.menu.categories.update', $category),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'category' => $category,
    ])
@endsection
