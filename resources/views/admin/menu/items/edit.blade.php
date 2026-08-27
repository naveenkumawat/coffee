@extends('layouts.admin')

@section('content')
    @include('admin.menu.items.partials.form', [
        'title' => 'Edit menu item',
        'action' => route('admin.menu.items.update', $item),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'item' => $item,
        'categories' => $categories,
    ])
@endsection
