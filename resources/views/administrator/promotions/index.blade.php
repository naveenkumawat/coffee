@extends('administrator.layouts.default')

@section('page-title', 'Offers & Promotions')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Offers & Promotions'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Offer', 'url' => route('administrator.promotions.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="alert alert-primary mb-8">
        Automatic offers apply at checkout without a code. Coupons require a promo code on the cart.
        Archived offers stay on historical orders and are hidden from new checkouts.
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Name</th>
                            <th>Type</th>
                            <th>Discount</th>
                            <th>Scope</th>
                            <th>Validity</th>
                            <th>Usage</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($promotions as $promotion)
                            @php
                                $discountLabel = $promotion->discount_type === \App\Enums\PromotionDiscountType::Percentage
                                    ? rtrim(rtrim(number_format((float) $promotion->discount_value, 2, '.', ''), '0'), '.').'%'
                                    : '₹'.number_format((float) $promotion->discount_value, 2);
                                $validity = 'Always';
                                if ($promotion->starts_at || $promotion->ends_at) {
                                    $from = $promotion->starts_at?->format('d M Y') ?? '…';
                                    $to = $promotion->ends_at?->format('d M Y') ?? '…';
                                    $validity = $from.' – '.$to;
                                }
                            @endphp
                            <tr>
                                <td>
                                    <span class="text-gray-900 fw-bold">{{ $promotion->name }}</span>
                                    @if ($promotion->code)
                                        <div class="text-muted fs-7">{{ $promotion->code }}</div>
                                    @endif
                                </td>
                                <td>{{ $promotion->type->label() }}</td>
                                <td>{{ $discountLabel }}</td>
                                <td>{{ $promotion->fulfilment_scope->label() }}</td>
                                <td>{{ $validity }}</td>
                                <td>
                                    {{ $promotion->order_promotions_count }}
                                    @if ($promotion->usage_limit)
                                        / {{ $promotion->usage_limit }}
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $promotion->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $promotion->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'Edit', 'url' => route('administrator.promotions.edit', $promotion), 'icon' => 'ki-notepad-edit'],
                                        [
                                            'label' => $promotion->is_active ? 'Deactivate' : 'Activate',
                                            'url' => route('administrator.promotions.toggle', $promotion),
                                            'method' => 'PATCH',
                                            'icon' => $promotion->is_active ? 'ki-cross-circle' : 'ki-check-circle',
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.promotions.destroy', $promotion),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this offer? Historical orders keep the discount snapshot.',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-10">No offers yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $promotions->links('components.internal.pagination') }}
            </div>
        </div>
    </div>
@endsection
