@php
    /** @var \App\Models\Order $order */
    $showFinancialSummary = (bool) ($showFinancialSummary ?? false);
    $showPaymentBadge = (bool) ($showPaymentBadge ?? $showFinancialSummary);
    $customerName = $order->customer?->name ?: ($order->customer_name ?: 'Walk-in / internal order');
    $fulfilment = $order->fulfilment_method;
@endphp

<div class="card card-flush internal-card mb-5">
    <div class="card-body py-5">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-4">
            <div class="min-w-0">
                <div class="text-muted fs-8 text-uppercase mb-1">Order</div>
                <h2 class="fw-bold text-gray-900 mb-3 user-select-all">{{ $order->order_number }}</h2>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <x-internal.order-status-badge :status="$order->status" :order="$order" />
                    @if ($showPaymentBadge)
                        <span class="badge {{ $order->payment_status?->badgeClass() ?? 'badge-light' }}">
                            {{ $order->payment_status?->label() ?? 'Pending' }}
                        </span>
                    @endif
                    @if ($fulfilment)
                        <span class="badge {{ $fulfilment->badgeClass() }} text-uppercase">
                            {{ $fulfilment->label() }}
                        </span>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-6 fs-7">
                    <div>
                        <div class="text-muted fs-8 text-uppercase">Customer</div>
                        <div class="fw-semibold text-gray-900">{{ $customerName }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-8 text-uppercase">Created</div>
                        <div class="fw-semibold text-gray-900">{{ $order->placed_at?->format('d M Y, h:i A') ?: '—' }}</div>
                    </div>
                    @if ($showFinancialSummary)
                        <div>
                            <div class="text-muted fs-8 text-uppercase">Total</div>
                            <div class="fw-bold text-gray-900">Rs {{ number_format((float) $order->total_amount, 2) }}</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="text-end min-w-125px">
                @if ($order->isDineIn())
                    <div class="text-muted fs-8 text-uppercase mb-1">Table</div>
                    <div class="fw-bold fs-2 text-primary text-uppercase">
                        {{ $order->tableDisplayLabel() ?: '—' }}
                    </div>
                @elseif ($order->isDelivery())
                    <div class="text-muted fs-8 text-uppercase mb-1">Delivery</div>
                    <div class="fw-semibold text-gray-900">
                        {{ $order->delivery_contact_name ?: $customerName }}
                    </div>
                    <div class="text-gray-700 fs-8 mt-1" style="white-space: pre-wrap; max-width: 16rem; margin-left: auto;">
                        {{ \Illuminate\Support\Str::limit((string) $order->delivery_address, 120) }}
                    </div>
                @else
                    <div class="text-muted fs-8 text-uppercase mb-1">Takeaway</div>
                    <div class="fw-semibold text-gray-900">Pickup</div>
                    <div class="text-gray-700 fs-8 mt-1">
                        {{ $order->pickup_name ?: $customerName }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
