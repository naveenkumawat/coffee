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
    @include('administrator.loyalty-rewards.partials.form', [
        'title' => 'Edit loyalty reward',
        'action' => route('administrator.loyalty-rewards.update', $reward),
        'method' => 'PUT',
    ])
@endsection
