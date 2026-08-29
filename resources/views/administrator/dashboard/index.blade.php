@extends('administrator.layouts.default')

@section('page-title', 'Administrator Dashboard')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Dashboard'],
    ]" />
@endsection

@section('toolbar-actions')
    <a href="{{ route('administrator.menu.items.create') }}" class="btn btn-primary">
        <i class="ki-outline ki-plus fs-2"></i>
        Add Menu Item
    </a>
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
        <div class="col-md-4">
            <x-internal.stat-card label="Menu Categories" :value="$categoryCount" icon="ki-category" color="warning" description="Shared catalog groups available for the storefront and future internal ordering flows." />
        </div>
        <div class="col-md-4">
            <x-internal.stat-card label="Menu Items" :value="$itemCount" icon="ki-basket" color="primary" description="Maintain the product catalog from the administrator side without affecting the public theme layer." />
        </div>
        <div class="col-md-4">
            <x-internal.stat-card label="Theme Layer" value="1" icon="ki-layer" color="success" description="Administrator and Barista share one internal asset and component foundation." />
        </div>
    </div>

    <div class="row g-5 g-xl-10">
        <div class="col-xl-8">
            <div class="card card-flush h-xl-100 internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Latest catalog activity</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="table-responsive internal-table-wrapper">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th>Entry</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-600">
                                @forelse ($latestItems as $item)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-900 fw-bold">{{ $item->name }}</span>
                                                <span class="text-muted">{{ $item->category?->name }} • ${{ number_format((float) $item->price, 2) }}</span>
                                            </div>
                                        </td>
                                        <td>Menu item</td>
                                        <td>
                                            <span class="badge {{ $item->is_available ? 'badge-light-success' : 'badge-light-danger' }}">
                                                {{ $item->is_available ? 'Available' : 'Paused' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    @forelse ($latestCategories as $category)
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="text-gray-900 fw-bold">{{ $category->name }}</span>
                                                    <span class="text-muted">{{ $category->menu_items_count }} linked menu items</span>
                                                </div>
                                            </td>
                                            <td>Category</td>
                                            <td>
                                                <span class="badge {{ $category->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                                    {{ $category->is_active ? 'Active' : 'Hidden' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-10">No administrator module data yet.</td>
                                        </tr>
                                    @endforelse
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush h-xl-100 internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Foundation notes</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-6 mb-6">
                        <i class="ki-outline ki-brush fs-2tx text-primary me-4"></i>
                        <div class="d-flex flex-column">
                            <h4 class="text-gray-900 fw-bold mb-1">ZYLM-style internal shell</h4>
                            <span class="fs-6 text-gray-700">Shared layout, header, sidebar, cards, tables, alerts, pagination, and modal patterns now live in a common internal layer.</span>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h4 class="fw-bold text-gray-900 mb-3">What stays separate</h4>
                        <div class="text-gray-700">Homepage, customer navigation, product discovery, checkout, and all public-facing frontend experiences continue to use their own Coffee theme.</div>
                    </div>

                    <button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#internalFoundationModal">
                        Review shared UI foundation
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
