@extends('administrator.layouts.default')

@section('page-title', 'Section Products')

@section('page-description', 'Manual product assignment and ordering for “'.$section->title.'”.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Homepage Sections', 'url' => route('administrator.home-sections.index')],
        ['label' => $section->title, 'url' => route('administrator.home-sections.edit', $section)],
        ['label' => 'Products'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Edit section', 'url' => route('administrator.home-sections.edit', $section), 'variant' => 'dark', 'icon' => 'ki-notepad-edit'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="POST" action="{{ route('administrator.home-sections.products.attach', $section) }}" class="row g-6 align-items-end internal-filter-form">
                @csrf
                <div class="col-xl-8 col-md-8">
                    <label for="product_id" class="form-label">Add product</label>
                    <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                        <option value="">Select an active product</option>
                        @foreach ($productOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('product_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-xl-4 col-md-4">
                    <x-internal.button-group :items="[
                        ['label' => 'Add product', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-plus'],
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
                            <th>Category</th>
                            <th>Launch</th>
                            <th>Sort</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($section->sectionProducts as $assignment)
                            @php
                                $assignedProduct = $assignment->product;
                                $report = $assignedProduct
                                    ? ($readinessReports[(int) $assignedProduct->id] ?? null)
                                    : null;
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $assignedProduct?->name ?: 'Deleted product' }}</span>
                                        @if ($assignedProduct && (! $assignedProduct->is_active || ! $assignedProduct->is_available))
                                            <span class="text-muted fs-8">Not customer-visible (inactive or paused). Assignment kept.</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $assignedProduct?->category?->name ?: '—' }}</td>
                                <td>
                                    @if ($report)
                                        <span class="badge {{ $report->isReady() ? 'badge-light-success' : 'badge-light-danger' }}">
                                            {{ $report->statusLabel() }}
                                        </span>
                                        @if (! $report->isReady())
                                            <div class="text-muted fs-8 mt-1">{{ implode('; ', $report->missing) }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $assignment->sort_order }}</td>
                                <td class="text-end internal-action-cell">
                                    @if ($assignment->product)
                                        <x-internal.action-dropdown :items="[
                                            [
                                                'label' => 'Move up',
                                                'url' => route('administrator.home-sections.products.move-up', [$section, $assignment->product]),
                                                'method' => 'PATCH',
                                                'icon' => 'ki-arrow-up',
                                            ],
                                            [
                                                'label' => 'Move down',
                                                'url' => route('administrator.home-sections.products.move-down', [$section, $assignment->product]),
                                                'method' => 'PATCH',
                                                'icon' => 'ki-arrow-down',
                                            ],
                                            ['type' => 'separator'],
                                            [
                                                'label' => 'Remove',
                                                'url' => route('administrator.home-sections.products.detach', [$section, $assignment->product]),
                                                'method' => 'DELETE',
                                                'icon' => 'ki-trash',
                                                'danger' => true,
                                                'confirm' => 'Remove this product from the section?',
                                            ],
                                        ]" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No products assigned yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
