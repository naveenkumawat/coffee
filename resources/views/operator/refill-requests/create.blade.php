@extends('operator.layouts.default')

@section('page-title', 'Create Refill Request')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Operator Panel', 'url' => route('operator.dashboard')],
        ['label' => 'Refill Requests', 'url' => route('operator.refill-requests.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('operator.refill-requests.partials.form', [
        'title' => 'New refill request',
        'action' => route('operator.refill-requests.store'),
        'ingredientOptions' => $ingredientOptions,
    ])
@endsection
