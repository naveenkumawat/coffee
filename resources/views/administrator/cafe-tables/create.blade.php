@extends('administrator.layouts.default')

@section('page-title', 'Create Café Table')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Café Tables', 'url' => route('administrator.cafe-tables.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.cafe-tables.partials.form', [
        'title' => 'Create café table',
        'action' => route('administrator.cafe-tables.store'),
        'method' => 'POST',
        'submit' => 'Create table',
        'table' => $table,
    ])
@endsection
