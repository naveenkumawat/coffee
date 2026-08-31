@extends('administrator.layouts.default')

@section('page-title', 'Create Social Link')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Social Links', 'url' => route('administrator.social-links.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.social-links.partials.form', [
        'title' => 'Create social link',
        'action' => route('administrator.social-links.store'),
        'method' => 'POST',
        'submit' => 'Create link',
        'link' => $link,
        'iconOptions' => $iconOptions,
    ])
@endsection
