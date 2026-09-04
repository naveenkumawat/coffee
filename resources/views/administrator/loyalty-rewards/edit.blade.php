@extends('administrator.layouts.default')

@section('page-title', 'Edit Loyalty Reward')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Loyalty Rewards', 'url' => route('administrator.loyalty-rewards.index')],
        ['label' => $reward->name],
    ]" />
@endsection

@section('content')
    @if ($performance)
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6">
                <div class="card-title">
                    <h3 class="fw-bold">Performance ({{ $performancePeriod['start_local']->format('d M') }} – {{ $performancePeriod['end_local']->format('d M Y') }})</h3>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="row g-4">
                    <div class="col-md-2"><div class="text-muted fs-8">Views</div><div class="fw-bold">{{ $performance['views'] }}</div></div>
                    <div class="col-md-2"><div class="text-muted fs-8">Selections</div><div class="fw-bold">{{ $performance['selections'] }}</div></div>
                    <div class="col-md-2"><div class="text-muted fs-8">Redeems</div><div class="fw-bold">{{ $performance['redemptions'] }}</div></div>
                    <div class="col-md-2"><div class="text-muted fs-8">Points</div><div class="fw-bold">{{ $performance['points_consumed'] }}</div></div>
                    <div class="col-md-2"><div class="text-muted fs-8">Discount</div><div class="fw-bold">₹{{ $performance['discount_value'] }}</div></div>
                    <div class="col-md-2"><div class="text-muted fs-8">Customers</div><div class="fw-bold">{{ $performance['unique_customers'] }}</div></div>
                </div>
                <div class="text-muted fs-8 mt-4">
                    Conversion uses recorded behaviour events only. Zero denominators show as —. Server redemption remains canonical.
                    View→Select {{ $performance['view_to_select_percent'] !== null ? $performance['view_to_select_percent'].'%' : '—' }}
                    · Select→Redeem {{ $performance['select_to_redeem_percent'] !== null ? $performance['select_to_redeem_percent'].'%' : '—' }}
                </div>
            </div>
        </div>
    @endif

    @include('administrator.loyalty-rewards.partials.form', [
        'title' => 'Edit loyalty reward',
        'action' => route('administrator.loyalty-rewards.update', $reward),
        'method' => 'PUT',
    ])
@endsection
