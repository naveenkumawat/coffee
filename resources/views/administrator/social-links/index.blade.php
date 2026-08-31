@extends('administrator.layouts.default')

@section('page-title', 'Social Links')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Social Links'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Social Link', 'url' => route('administrator.social-links.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="alert alert-primary mb-8">
        Customer footer shows only active links with a valid URL. WhatsApp may leave URL blank to use the Website Settings WhatsApp number (<code>wa.me</code>).
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Platform</th>
                            <th>Icon</th>
                            <th>URL</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($links as $link)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $link->label }}</span>
                                        <span class="text-muted">{{ $link->platform_key }}</span>
                                    </div>
                                </td>
                                <td>{{ $link->icon_key }}</td>
                                <td class="text-break">
                                    @if (filled($link->url))
                                        <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">{{ $link->url }}</a>
                                    @elseif ($link->platform_key === \App\Models\SocialLink::PLATFORM_WHATSAPP)
                                        <span class="text-muted">Uses Website Settings WhatsApp</span>
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </td>
                                <td>{{ $link->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $link->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $link->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'Edit', 'url' => route('administrator.social-links.edit', $link), 'icon' => 'ki-notepad-edit'],
                                        [
                                            'label' => 'Move up',
                                            'url' => route('administrator.social-links.move-up', $link),
                                            'method' => 'PATCH',
                                            'icon' => 'ki-arrow-up',
                                        ],
                                        [
                                            'label' => 'Move down',
                                            'url' => route('administrator.social-links.move-down', $link),
                                            'method' => 'PATCH',
                                            'icon' => 'ki-arrow-down',
                                        ],
                                        [
                                            'label' => $link->is_active ? 'Deactivate' : 'Activate',
                                            'url' => route('administrator.social-links.toggle', $link),
                                            'method' => 'PATCH',
                                            'icon' => $link->is_active ? 'ki-cross-circle' : 'ki-check-circle',
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.social-links.destroy', $link),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this social link?',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">No social links yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $links->links('components.internal.pagination') }}
            </div>
        </div>
    </div>
@endsection
