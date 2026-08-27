@extends('layouts.admin')

@section('content')
    @include('admin.menu.items.partials.form', [
        'title' => 'Create menu item',
        'action' => route('admin.menu.items.store'),
        'method' => 'POST',
        'submit' => 'Create item',
        'item' => $item,
        'categories' => $categories,
    ])
@endsection
