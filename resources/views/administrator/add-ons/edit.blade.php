@extends('administrator.layouts.default')

@section('page-title', 'Edit Add-on')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Add-ons', 'url' => route('administrator.add-ons.index')],
        ['label' => 'Edit'],
    ]" />
@endsection

@section('content')
    @include('administrator.add-ons.partials.form', [
        'title' => 'Edit add-on',
        'action' => route('administrator.add-ons.update', $addOn),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'addOn' => $addOn,
        'ingredientOptions' => $ingredientOptions,
        'unitOptions' => $unitOptions,
        'lineRows' => $lineRows,
    ])
@endsection
