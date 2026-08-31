@extends('administrator.layouts.default')

@section('page-title', 'Rating Detail')

@section('page-description', 'Verified purchase context and moderation history.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ratings & Reviews', 'url' => route('administrator.products.ratings.index')],
        ['label' => 'Detail'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.products.ratings.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
@endsection

@section('content')
    <div class="row g-7">
        <div class="col-xl-8">
            <div class="card card-flush internal-card mb-7">
                <div class="card-header">
                    <div class="card-title">
                        <h2 class="fw-bold">{{ $rating->product?->name ?: 'Deleted product' }}</h2>
                    </div>
                    <div class="card-toolbar">
                        <x-internal.action-dropdown :items="[
                            [
                                'label' => 'Hide review',
                                'url' => route('administrator.products.ratings.hide', $rating),
                                'method' => 'PATCH',
                                'icon' => 'ki-eye-slash',
                                'disabled' => ! $rating->is_public,
                                'confirm' => 'Hide this review text from the public catalog?',
                            ],
                            [
                                'label' => 'Publish review',
                                'url' => route('administrator.products.ratings.publish', $rating),
                                'method' => 'PATCH',
                                'icon' => 'ki-check-circle',
                                'disabled' => $rating->is_public,
                            ],
                            ['type' => 'separator'],
                            [
                                'label' => 'Delete',
                                'url' => route('administrator.products.ratings.destroy', $rating),
                                'method' => 'DELETE',
                                'icon' => 'ki-trash',
                                'danger' => true,
                                'confirm' => 'Permanently delete this rating?',
                            ],
                        ]" />
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-6">
                        <span class="text-muted d-block mb-1">Rating</span>
                        <span class="fs-2 fw-bold text-gray-900">{{ $rating->rating }}/5</span>
                    </div>
                    <div class="mb-6">
                        <span class="text-muted d-block mb-1">Review</span>
                        @if (filled($rating->review))
                            <p class="fs-6 text-gray-800 mb-0" style="white-space: pre-wrap;">{{ $rating->review }}</p>
                        @else
                            <p class="text-muted mb-0">No written review</p>
                        @endif
                    </div>
                    <div class="mb-0">
                        <span class="text-muted d-block mb-1">Visibility</span>
                        @if ($rating->is_public)
                            <span class="badge badge-light-primary">Public</span>
                        @else
                            <span class="badge badge-light-warning">Hidden</span>
                        @endif
                        <p class="text-muted fs-7 mt-3 mb-0">
                            Hiding conceals review text only. The numeric score still contributes to average and count until the rating is deleted.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush internal-card mb-7">
                <div class="card-header">
                    <h3 class="card-title">Customer</h3>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-4">
                        <span class="text-muted d-block">Name</span>
                        <span class="fw-bold text-gray-900">{{ $rating->customer?->name }}</span>
                    </div>
                    <div class="mb-0">
                        <span class="text-muted d-block">Email</span>
                        <span>{{ $rating->customer?->email }}</span>
                    </div>
                </div>
            </div>

            <div class="card card-flush internal-card mb-7">
                <div class="card-header">
                    <h3 class="card-title">Purchase context</h3>
                </div>
                <div class="card-body pt-0">
                    @if ($rating->qualifyingOrder)
                        <div class="mb-4">
                            <span class="text-muted d-block">Order</span>
                            <span class="fw-bold text-gray-900">{{ $rating->qualifyingOrder->order_number }}</span>
                        </div>
                        <div class="mb-0">
                            <span class="text-muted d-block">Completed</span>
                            <span>{{ $rating->qualifyingOrder->completed_at?->format('d M Y, h:i A') ?: '—' }}</span>
                        </div>
                    @else
                        <p class="text-muted mb-0">No qualifying order linked.</p>
                    @endif
                </div>
            </div>

            <div class="card card-flush internal-card">
                <div class="card-header">
                    <h3 class="card-title">Timestamps</h3>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-4">
                        <span class="text-muted d-block">Submitted</span>
                        <span>{{ $rating->created_at?->format('d M Y, h:i A') }}</span>
                    </div>
                    <div class="mb-4">
                        <span class="text-muted d-block">Updated</span>
                        <span>{{ $rating->updated_at?->format('d M Y, h:i A') }}</span>
                    </div>
                    <div class="mb-4">
                        <span class="text-muted d-block">Moderated</span>
                        <span>{{ $rating->moderated_at?->format('d M Y, h:i A') ?: '—' }}</span>
                    </div>
                    <div class="mb-0">
                        <span class="text-muted d-block">Moderator</span>
                        <span>{{ $rating->moderator?->name ?: '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
