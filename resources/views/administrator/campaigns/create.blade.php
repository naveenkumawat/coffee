@extends('administrator.layouts.default')

@section('page-title', 'Create Campaign')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Campaigns', 'url' => route('administrator.campaigns.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.campaigns.partials.form', [
        'title' => 'Create campaign',
        'action' => route('administrator.campaigns.store'),
        'method' => 'POST',
        'submit' => 'Create campaign',
        'campaign' => $campaign,
    ])
@endsection
