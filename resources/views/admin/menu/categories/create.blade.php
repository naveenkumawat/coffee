@extends('layouts.admin')

@section('content')
    @include('admin.menu.categories.partials.form', [
        'title' => 'Create menu category',
        'action' => route('admin.menu.categories.store'),
        'method' => 'POST',
        'submit' => 'Create category',
        'category' => $category,
    ])
@endsection
