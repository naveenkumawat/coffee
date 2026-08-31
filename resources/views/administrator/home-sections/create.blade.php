@extends('administrator.layouts.default')

@section('page-title', 'New Homepage Section')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Homepage Sections', 'url' => route('administrator.home-sections.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.home-sections.partials.form', [
        'title' => 'Create section',
        'action' => route('administrator.home-sections.store'),
        'method' => 'POST',
        'submit' => 'Create section',
        'section' => $section,
    ])
@endsection
