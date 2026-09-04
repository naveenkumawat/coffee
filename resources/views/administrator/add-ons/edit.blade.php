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
    @if (($productUsageCount ?? 0) > 0)
        <div class="alert alert-light border mb-6">
            Used on {{ $productUsageCount }} product{{ $productUsageCount === 1 ? '' : 's' }}. Recipe and selling price are configured per product.
        </div>
    @endif
    @include('administrator.add-ons.partials.form', [
        'title' => 'Edit add-on',
        'action' => route('administrator.add-ons.update', $addOn),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'addOn' => $addOn,
    ])
@endsection
