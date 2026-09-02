@extends('administrator.layouts.default')

@section('page-title', 'Create Add-on')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Add-ons', 'url' => route('administrator.add-ons.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.add-ons.partials.form', [
        'title' => 'Create add-on',
        'action' => route('administrator.add-ons.store'),
        'method' => 'POST',
        'submit' => 'Create add-on',
        'addOn' => $addOn,
        'ingredientOptions' => $ingredientOptions,
        'unitOptions' => $unitOptions,
        'lineRows' => $lineRows,
    ])
@endsection
