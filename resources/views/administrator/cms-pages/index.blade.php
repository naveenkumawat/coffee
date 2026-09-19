@extends('administrator.layouts.default')

@section('page-title', 'Pages')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Pages'],
    ]" />
@endsection

@section('content')
    <div class="alert alert-primary mb-8">
        These are required customer pages (About, Visit, FAQ, Terms, Privacy). They cannot be deleted. Unpublished pages show a safe empty state on the customer site.
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Title</th>
                            <th>Key</th>
                            <th>Status</th>
                            <th>Last updated</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($pages as $page)
                            <tr>
                                <td class="text-gray-900 fw-bold">{{ $page->title }}</td>
                                <td><code>{{ $page->key }}</code></td>
                                <td>
                                    <span class="badge {{ $page->is_published ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $page->is_published ? 'Published' : 'Unpublished' }}
                                    </span>
                                </td>
                                <td>{{ optional($page->updated_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="array_values(array_filter([
                                        ['label' => 'Edit', 'url' => route('administrator.cms-pages.edit', $page), 'icon' => 'ki-notepad-edit'],
                                        filled($customerBaseUrl)
                                            ? ['label' => 'Preview', 'url' => $customerBaseUrl.($page->pageKey()?->customerPath() ?? '/'), 'icon' => 'ki-eye', 'target' => '_blank']
                                            : null,
                                    ]))" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No pages found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
