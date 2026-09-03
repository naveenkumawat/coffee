@extends('administrator.layouts.default')

@section('page-title', 'Edit Campaign')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Campaigns', 'url' => route('administrator.campaigns.index')],
        ['label' => $campaign->name],
    ]" />
@endsection

@section('content')
    @include('administrator.campaigns.partials.form', [
        'title' => 'Edit campaign',
        'action' => route('administrator.campaigns.update', $campaign),
        'method' => 'PUT',
        'submit' => 'Save campaign',
        'campaign' => $campaign,
    ])
@endsection
