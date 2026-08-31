@extends('administrator.layouts.default')

@section('page-title', 'Edit Social Link')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Social Links', 'url' => route('administrator.social-links.index')],
        ['label' => $link->label],
    ]" />
@endsection

@section('content')
    @include('administrator.social-links.partials.form', [
        'title' => 'Edit social link',
        'action' => route('administrator.social-links.update', $link),
        'method' => 'PUT',
        'submit' => 'Save link',
        'link' => $link,
        'iconOptions' => $iconOptions,
    ])
@endsection
