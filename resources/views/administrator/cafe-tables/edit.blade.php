@extends('administrator.layouts.default')

@section('page-title', 'Edit Café Table')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Café Tables', 'url' => route('administrator.cafe-tables.index')],
        ['label' => $table->code],
    ]" />
@endsection

@section('content')
    @include('administrator.cafe-tables.partials.form', [
        'title' => 'Edit café table',
        'action' => route('administrator.cafe-tables.update', $table),
        'method' => 'PUT',
        'submit' => 'Save table',
        'table' => $table,
    ])
@endsection
