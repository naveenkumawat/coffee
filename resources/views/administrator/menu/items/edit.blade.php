@extends('administrator.layouts.default')

@section('page-title', 'Edit Menu Item')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Menu Items', 'url' => route('administrator.menu.items.index')],
        ['label' => 'Edit'],
    ]" />
@endsection

@section('content')
    @include('administrator.menu.items.partials.form', [
        'title' => 'Edit menu item',
        'action' => route('administrator.menu.items.update', $item),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'item' => $item,
        'categories' => $categories,
    ])
@endsection
