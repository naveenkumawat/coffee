@extends('administrator.layouts.default')

@section('page-title', 'Loyalty Rewards')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Loyalty Rewards'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Loyalty Operations', 'url' => route('administrator.loyalty-operations.index'), 'variant' => 'dark', 'icon' => 'ki-chart-pie-4'],
        ['label' => 'New Reward', 'url' => route('administrator.loyalty-rewards.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="alert alert-primary mb-8">
        Customers redeem these catalog rewards with loyalty points at checkout. Archived rewards stay on historical orders via snapshots. Prefer archive over delete.
    </div>

    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.loyalty-rewards.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-3 col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="all" @selected($filters['status'] === 'all')>All active statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-4">
                    <div class="form-check form-check-custom form-check-solid mt-8">
                        <input class="form-check-input" type="checkbox" value="1" id="include_archived" name="include_archived" @checked($filters['include_archived']) />
                        <label class="form-check-label" for="include_archived">Include archived</label>
                    </div>
                </div>
                <div class="col-xl-3 col-md-4">
                    <x-internal.button-group :items="[
                        ['label' => 'Apply', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.loyalty-rewards.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
                    ]" justify="start" />
                </div>
            </form>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('administrator.loyalty-rewards.bulk-status') }}"
        id="loyalty-rewards-bulk-form"
        data-confirm-title="Apply bulk status?"
        data-confirm-body="This updates the status for all selected loyalty rewards."
        data-confirm-label="Apply to selected"
        data-confirm-class="btn-warning"
    >
        @csrf
        <div class="card card-flush internal-card mb-5">
            <div class="card-body pt-5">
                <div class="row g-4 align-items-end">
                    <div class="col-md-3">
                        <label for="bulk_status" class="form-label">Bulk action</label>
                        <select id="bulk_status" name="status" class="form-select" required>
                            <option value="{{ \App\Enums\LoyaltyRewardStatus::Paused->value }}">Pause selected</option>
                            <option value="{{ \App\Enums\LoyaltyRewardStatus::Active->value }}">Activate selected</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" value="1" id="bulk_confirmed" name="confirmed" required />
                            <label class="form-check-label" for="bulk_confirmed">I confirm this bulk status change</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-light-warning">
                            Apply to selected
                        </button>
                    </div>
                </div>
                @error('reward_ids')
                    <div class="text-danger fs-7 mt-3">{{ $message }}</div>
                @enderror
                @error('confirmed')
                    <div class="text-danger fs-7 mt-3">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="card card-flush internal-card">
            <div class="card-body pt-0">
                <div class="table-responsive internal-table-wrapper">
                    <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th style="width: 40px;"></th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Points</th>
                                <th>Validity</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th class="text-end internal-action-header">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            @forelse ($rewards as $reward)
                                @php
                                    $validity = 'Always';
                                    if ($reward->starts_at || $reward->ends_at) {
                                        $from = $reward->starts_at?->format('d M Y') ?? '…';
                                        $to = $reward->ends_at?->format('d M Y') ?? '…';
                                        $validity = $from.' – '.$to;
                                    }
                                    $isArchived = $reward->trashed() || $reward->status === \App\Enums\LoyaltyRewardStatus::Archived;
                                @endphp
                                <tr>
                                    <td>
                                        @unless ($isArchived)
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" name="reward_ids[]" value="{{ $reward->id }}" />
                                            </div>
                                        @endunless
                                    </td>
                                    <td>
                                        <span class="text-gray-900 fw-bold">{{ $reward->name }}</span>
                                        @if ($reward->customer_description)
                                            <div class="text-muted fs-7">{{ $reward->customer_description }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $reward->reward_type->label() }}</td>
                                    <td>{{ $reward->points_cost }}</td>
                                    <td>{{ $validity }}</td>
                                    <td>
                                        {{ $reward->orders_count }}
                                        @if ($reward->usage_limit)
                                            / {{ $reward->usage_limit }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $reward->status === \App\Enums\LoyaltyRewardStatus::Active ? 'badge-light-success' : 'badge-light-warning' }}">
                                            {{ $reward->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-end internal-action-cell">
                                        <x-internal.action-dropdown :items="array_values(array_filter([
                                            ['label' => 'Edit', 'url' => route('administrator.loyalty-rewards.edit', $reward), 'icon' => 'ki-notepad-edit'],
                                            [
                                                'label' => 'Duplicate',
                                                'url' => route('administrator.loyalty-rewards.duplicate', $reward),
                                                'method' => 'POST',
                                                'icon' => 'ki-copy',
                                            ],
                                            ! $isArchived && $reward->status === \App\Enums\LoyaltyRewardStatus::Active
                                                ? [
                                                    'label' => 'Pause',
                                                    'url' => route('administrator.loyalty-rewards.status', [$reward, \App\Enums\LoyaltyRewardStatus::Paused->value]),
                                                    'method' => 'PATCH',
                                                    'icon' => 'ki-cross-circle',
                                                ]
                                                : null,
                                            ! $isArchived && $reward->status !== \App\Enums\LoyaltyRewardStatus::Active
                                                ? [
                                                    'label' => 'Activate',
                                                    'url' => route('administrator.loyalty-rewards.status', [$reward, \App\Enums\LoyaltyRewardStatus::Active->value]),
                                                    'method' => 'PATCH',
                                                    'icon' => 'ki-check-circle',
                                                ]
                                                : null,
                                            ! $isArchived ? ['type' => 'separator'] : null,
                                            ! $isArchived
                                                ? [
                                                    'label' => 'Archive',
                                                    'url' => route('administrator.loyalty-rewards.destroy', $reward),
                                                    'method' => 'DELETE',
                                                    'icon' => 'ki-trash',
                                                    'danger' => true,
                                                    'confirm' => 'Archive this loyalty reward? Historical order snapshots stay intact.',
                                                ]
                                                : null,
                                        ]))" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-10">No loyalty rewards yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $rewards->links('components.internal.pagination') }}
                </div>
            </div>
        </div>
    </form>
@endsection
