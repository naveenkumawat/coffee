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
        ['label' => 'New Reward', 'url' => route('administrator.loyalty-rewards.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="alert alert-primary mb-8">
        Customers redeem these catalog rewards with loyalty points at checkout. Archived rewards stay on historical orders via snapshots.
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
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
                            @endphp
                            <tr>
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
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'Edit', 'url' => route('administrator.loyalty-rewards.edit', $reward), 'icon' => 'ki-notepad-edit'],
                                        ...(
                                            $reward->status === \App\Enums\LoyaltyRewardStatus::Active
                                                ? [[
                                                    'label' => 'Pause',
                                                    'url' => route('administrator.loyalty-rewards.status', [$reward, \App\Enums\LoyaltyRewardStatus::Paused->value]),
                                                    'method' => 'PATCH',
                                                    'icon' => 'ki-cross-circle',
                                                ]]
                                                : [[
                                                    'label' => 'Activate',
                                                    'url' => route('administrator.loyalty-rewards.status', [$reward, \App\Enums\LoyaltyRewardStatus::Active->value]),
                                                    'method' => 'PATCH',
                                                    'icon' => 'ki-check-circle',
                                                ]]
                                        ),
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.loyalty-rewards.destroy', $reward),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this loyalty reward?',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-10">No loyalty rewards yet.</td>
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
@endsection
