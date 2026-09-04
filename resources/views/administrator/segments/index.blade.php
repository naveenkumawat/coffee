@extends('administrator.layouts.default')

@section('page-title', 'Audience Segments')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Audience Segments'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Segment', 'url' => route('administrator.segments.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="alert alert-primary mb-8">
        Segments are reusable named audiences evaluated dynamically from profiles, favourites and completed orders.
        Campaigns reference segments — they do not copy segment rule JSON.
    </div>

    <form method="GET" class="mb-6 d-flex gap-3 align-items-end">
        <div>
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-light-primary">Filter</button>
    </form>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Name</th>
                            <th>Scope</th>
                            <th>Rules</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($segments as $segment)
                            @php
                                $badge = match ($segment->status) {
                                    \App\Enums\AudienceSegmentStatus::Active => 'badge-light-success',
                                    \App\Enums\AudienceSegmentStatus::Paused => 'badge-light-warning',
                                    \App\Enums\AudienceSegmentStatus::Archived => 'badge-light-dark',
                                    default => 'badge-light-primary',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="text-gray-900 fw-bold">{{ $segment->name }}</span>
                                    <div class="text-muted fs-7">{{ $segment->slug }}</div>
                                </td>
                                <td>{{ $segment->actor_scope->label() }}</td>
                                <td class="fs-7">{{ \Illuminate\Support\Str::limit($segment->ruleSummary(), 90) }}</td>
                                <td><span class="badge {{ $badge }}">{{ $segment->status->label() }}</span></td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="array_values(array_filter([
                                        ['label' => 'Edit', 'url' => route('administrator.segments.edit', $segment), 'icon' => 'ki-notepad-edit'],
                                        $segment->status !== \App\Enums\AudienceSegmentStatus::Active
                                            ? ['label' => 'Activate', 'url' => route('administrator.segments.status', [$segment, 'active']), 'method' => 'PATCH', 'icon' => 'ki-check']
                                            : null,
                                        $segment->status === \App\Enums\AudienceSegmentStatus::Active
                                            ? ['label' => 'Pause', 'url' => route('administrator.segments.status', [$segment, 'paused']), 'method' => 'PATCH', 'icon' => 'ki-minus']
                                            : null,
                                        $segment->status !== \App\Enums\AudienceSegmentStatus::Archived
                                            ? ['label' => 'Archive status', 'url' => route('administrator.segments.status', [$segment, 'archived']), 'method' => 'PATCH', 'icon' => 'ki-cross']
                                            : null,
                                        [
                                            'label' => 'Delete',
                                            'url' => route('administrator.segments.destroy', $segment),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'confirm' => 'Archive this segment?',
                                        ],
                                    ]))" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No segments yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                {{ $segments->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
