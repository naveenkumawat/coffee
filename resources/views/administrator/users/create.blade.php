@extends('administrator.layouts.default')

@section('page-title', 'Create User')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Users', 'url' => route('administrator.users.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.users.partials.form', [
        'title' => 'Create user',
        'action' => route('administrator.users.store'),
        'method' => 'POST',
        'submit' => 'Create user',
        'managedUser' => $managedUser,
        'roleOptions' => $roleOptions,
        'selectedRole' => $selectedRole,
        'isEditing' => false,
    ])
@endsection
