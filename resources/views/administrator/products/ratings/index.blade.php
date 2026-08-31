@extends('administrator.layouts.default')

@section('page-title', 'Ratings & Reviews')

@section('page-description', 'Verified purchase ratings. Hiding a review conceals review text only; the star score still counts unless deleted.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Products', 'url' => route('administrator.products.index')],
        ['label' => 'Ratings & Reviews'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.products.ratings.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-4 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Product, customer, or review text" />
                </div>
                <div class="col-xl-3 col-md-6">
                    <label for="product_id" class="form-label">Product</label>
                    <select id="product_id" name="product_id" class="form-select">
                        <option value="">All products</option>
                        @foreach ($productOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('product_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="rating" class="form-label">Stars</label>
                    <select id="rating" name="rating" class="form-select">
                        <option value="">All</option>
                        @for ($star = 5; $star >= 1; $star--)
                            <option value="{{ $star }}" @selected((string) request('rating') === (string) $star)>{{ $star }} star{{ $star === 1 ? '' : 's' }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="is_public" class="form-label">Visibility</label>
                    <select id="is_public" name="is_public" class="form-select">
                        <option value="">All</option>
                        <option value="1" @selected(request('is_public') === '1')>Public</option>
                        <option value="0" @selected(request('is_public') === '0')>Hidden</option>
                    </select>
                </div>
                <div class="col-xl-3 col-md-12">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.products.ratings.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
                    ]" justify="start" />
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Review</th>
                            <th>Verified</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($ratings as $rating)
                            <tr>
                                <td>
                                    <span class="text-gray-900 fw-bold">{{ $rating->product?->name ?: 'Deleted product' }}</span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $rating->customer?->name ?: 'Unknown' }}</span>
                                        <span class="text-muted">{{ $rating->customer?->email }}</span>
                                    </div>
                                </td>
                                <td>{{ $rating->rating }}/5</td>
                                <td>
                                    @if (filled($rating->review))
                                        {{ \Illuminate\Support\Str::limit($rating->review, 80) }}
                                    @else
                                        <span class="text-muted">No written review</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($rating->qualifying_order_id)
                                        <span class="badge badge-light-success">Verified purchase</span>
                                    @else
                                        <span class="badge badge-light">Unlinked</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($rating->is_public)
                                        <span class="badge badge-light-primary">Public</span>
                                    @else
                                        <span class="badge badge-light-warning">Hidden</span>
                                    @endif
                                </td>
                                <td>{{ $rating->created_at?->format('d M Y, h:i A') }}</td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('administrator.products.ratings.show', $rating), 'icon' => 'ki-eye'],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Hide review',
                                            'url' => route('administrator.products.ratings.hide', $rating),
                                            'method' => 'PATCH',
                                            'icon' => 'ki-eye-slash',
                                            'disabled' => ! $rating->is_public,
                                            'confirm' => 'Hide this review text from the public catalog? The star score will still count.',
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
                                            'confirm' => 'Permanently delete this rating? Score and review will be removed from aggregates.',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-10">No ratings matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $ratings->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
