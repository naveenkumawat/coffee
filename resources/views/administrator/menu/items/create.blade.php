@extends('administrator.layouts.default')

@section('page-title', 'Create Menu Item')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Menu Items', 'url' => route('administrator.menu.items.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.menu.items.partials.form', [
        'title' => 'Create menu item',
        'action' => route('administrator.menu.items.store'),
        'method' => 'POST',
        'submit' => 'Create item',
        'item' => $item,
        'categories' => $categories,
    ])
@endsection
