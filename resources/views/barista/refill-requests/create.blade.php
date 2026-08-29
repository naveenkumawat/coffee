@extends('barista.layouts.default')

@section('page-title', 'Create Refill Request')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Refill Requests', 'url' => route('barista.refill-requests.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('barista.refill-requests.partials.form', [
        'title' => 'New refill request',
        'action' => route('barista.refill-requests.store'),
        'ingredientOptions' => $ingredientOptions,
    ])
@endsection
