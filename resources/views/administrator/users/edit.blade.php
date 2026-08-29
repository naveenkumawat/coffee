@extends('administrator.layouts.default')

@section('page-title', 'Edit User')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Users', 'url' => route('administrator.users.index')],
        ['label' => 'Edit'],
    ]" />
@endsection

@section('content')
    @include('administrator.users.partials.form', [
        'title' => 'Edit user',
        'action' => route('administrator.users.update', $managedUser),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'managedUser' => $managedUser,
        'roleOptions' => $roleOptions,
        'selectedRole' => $selectedRole,
        'isEditing' => true,
    ])
@endsection
