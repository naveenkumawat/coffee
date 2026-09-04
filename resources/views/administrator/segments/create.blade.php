@extends('administrator.layouts.default')

@section('page-title', 'Create Segment')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Audience Segments', 'url' => route('administrator.segments.index')],
        ['label' => 'Create'],
    ]" />
@endsection

@section('content')
    @include('administrator.segments.partials.form', [
        'title' => 'Create segment',
        'action' => route('administrator.segments.store'),
        'method' => 'POST',
        'submit' => 'Create segment',
        'segment' => $segment,
        'showPreview' => false,
    ])
@endsection
