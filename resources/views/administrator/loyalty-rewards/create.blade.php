@extends('administrator.layouts.default')

@section('page-title', 'Create Loyalty Reward')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Loyalty Rewards', 'url' => route('administrator.loyalty-rewards.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.loyalty-rewards.partials.form', [
        'title' => 'Create loyalty reward',
        'action' => route('administrator.loyalty-rewards.store'),
        'method' => 'POST',
    ])
@endsection
