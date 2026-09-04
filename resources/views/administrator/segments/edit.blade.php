@extends('administrator.layouts.default')

@section('page-title', 'Edit Segment')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Audience Segments', 'url' => route('administrator.segments.index')],
        ['label' => $segment->name],
    ]" />
@endsection

@section('content')
    @include('administrator.segments.partials.form', [
        'title' => 'Edit segment',
        'action' => route('administrator.segments.update', $segment),
        'method' => 'PUT',
        'submit' => 'Save segment',
        'segment' => $segment,
        'showPreview' => true,
    ])
@endsection
