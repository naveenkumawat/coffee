@extends('layouts.admin')

@section('content')
    @include('admin.menu.categories.partials.form', [
        'title' => 'Edit menu category',
        'action' => route('admin.menu.categories.update', $category),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'category' => $category,
    ])
@endsection
