@extends('administrator.layouts.default')

@section('page-title', 'Campaigns')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Campaigns'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Campaign', 'url' => route('administrator.campaigns.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="alert alert-primary mb-8">
        Campaigns drive contextual popups in the customer PWA. Targeting and frequency are evaluated server-side.
        Discount math stays in Offers &amp; Promotions — campaigns can link to a promotion as a CTA only.
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
                            <th>Surface</th>
                            <th>Priority</th>
                            <th>Schedule</th>
                            <th>Frequency</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($campaigns as $campaign)
                            @php
                                $validity = 'Always';
                                if ($campaign->starts_at || $campaign->ends_at) {
                                    $from = $campaign->starts_at?->format('d M Y') ?? '…';
                                    $to = $campaign->ends_at?->format('d M Y') ?? '…';
                                    $validity = $from.' – '.$to;
                                }
                                $badge = match ($campaign->status) {
                                    \App\Enums\CampaignStatus::Active => 'badge-light-success',
                                    \App\Enums\CampaignStatus::Paused => 'badge-light-warning',
                                    \App\Enums\CampaignStatus::Ended => 'badge-light-dark',
                                    default => 'badge-light-primary',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="text-gray-900 fw-bold">{{ $campaign->name }}</span>
                                    <div class="text-muted fs-7">{{ $campaign->title }}</div>
                                </td>
                                <td>{{ $campaign->surface->label() }}</td>
                                <td>{{ $campaign->priority }}</td>
                                <td>{{ $validity }}</td>
                                <td>{{ $campaign->frequency_policy->label() }}</td>
                                <td>
                                    <span class="badge {{ $badge }}">{{ $campaign->status->label() }}</span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="array_values(array_filter([
                                        ['label' => 'Edit', 'url' => route('administrator.campaigns.edit', $campaign), 'icon' => 'ki-notepad-edit'],
                                        $campaign->status !== \App\Enums\CampaignStatus::Active
                                            ? ['label' => 'Activate', 'url' => route('administrator.campaigns.status', [$campaign, 'active']), 'method' => 'PATCH', 'icon' => 'ki-check']
                                            : null,
                                        $campaign->status === \App\Enums\CampaignStatus::Active
                                            ? ['label' => 'Pause', 'url' => route('administrator.campaigns.status', [$campaign, 'paused']), 'method' => 'PATCH', 'icon' => 'ki-minus']
                                            : null,
                                        $campaign->status !== \App\Enums\CampaignStatus::Ended
                                            ? ['label' => 'End', 'url' => route('administrator.campaigns.status', [$campaign, 'ended']), 'method' => 'PATCH', 'icon' => 'ki-cross']
                                            : null,
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.campaigns.destroy', $campaign),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'confirm' => 'Archive this campaign?',
                                        ],
                                    ]))" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-10">No campaigns yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                {{ $campaigns->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
