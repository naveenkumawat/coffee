@extends('administrator.layouts.default')

@section('page-title', 'Menu Categories')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Menu Categories'],
    ]" />
@endsection

@section('toolbar-actions')
    <a href="{{ route('administrator.menu.categories.create') }}" class="btn btn-primary">
        <i class="ki-outline ki-plus fs-2"></i>
        New Category
    </a>
@endsection

@section('content')
    <div class="card card-flush">
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5 mb-0">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Category</th>
                            <th>Order</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $category->name }}</span>
                                        <span class="text-muted">{{ $category->description ?: 'No description provided.' }}</span>
                                    </div>
                                </td>
                                <td>{{ $category->sort_order }}</td>
                                <td>{{ $category->menu_items_count }}</td>
                                <td>
                                    <span class="badge {{ $category->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $category->is_active ? 'Active' : 'Hidden' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('administrator.menu.categories.edit', $category) }}" class="btn btn-sm btn-light-primary me-2">Edit</a>
                                    <form method="POST" action="{{ route('administrator.menu.categories.destroy', $category) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No menu categories created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $categories->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
