@extends('administrator.layouts.default')

@section('page-title', 'Edit Homepage Section')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Homepage Sections', 'url' => route('administrator.home-sections.index')],
        ['label' => $section->title],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Manage products', 'url' => route('administrator.home-sections.products', $section), 'variant' => 'primary', 'icon' => 'ki-basket'],
    ]" />
@endsection

@section('content')
    @include('administrator.home-sections.partials.form', [
        'title' => 'Edit section',
        'action' => route('administrator.home-sections.update', $section),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'section' => $section,
        'placementOptions' => $placementOptions,
        'sourceTypeOptions' => $sourceTypeOptions,
        'recommendationContextOptions' => $recommendationContextOptions,
        'categoryOptions' => $categoryOptions,
        'tagOptions' => $tagOptions,
    ])
@endsection
