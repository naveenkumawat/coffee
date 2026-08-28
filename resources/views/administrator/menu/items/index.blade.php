@extends('administrator.layouts.default')

@section('page-title', 'Menu Items')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Menu Items'],
    ]" />
@endsection

@section('toolbar-actions')
    <a href="{{ route('administrator.menu.items.create') }}" class="btn btn-primary">
        <i class="ki-outline ki-plus fs-2"></i>
        New Item
    </a>
@endsection

@section('content')
    <div class="card card-flush">
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5 mb-0">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Item</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Flags</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $item->name }}</span>
                                        <span class="text-muted">{{ $item->description ?: 'No description provided.' }}</span>
                                    </div>
                                </td>
                                <td>{{ $item->category?->name }}</td>
                                <td>${{ number_format((float) $item->price, 2) }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge {{ $item->is_available ? 'badge-light-success' : 'badge-light-danger' }}">
                                            {{ $item->is_available ? 'Available' : 'Paused' }}
                                        </span>
                                        @if ($item->is_featured)
                                            <span class="badge badge-light-warning">Featured</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('administrator.menu.items.edit', $item) }}" class="btn btn-sm btn-light-primary me-2">Edit</a>
                                    <form method="POST" action="{{ route('administrator.menu.items.destroy', $item) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No menu items created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $items->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
