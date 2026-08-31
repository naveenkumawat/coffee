@php
    /** @var \App\Models\Order $order */
    $customerName = $order->customer?->name ?: ($order->customer_name ?: 'Walk-in / internal order');
    $customerEmail = $order->customer?->email ?: $order->customer_email;
    $customerPhone = $order->customer?->phone ?: $order->customer_phone;
@endphp

<div class="card card-flush internal-card mb-5">
    <div class="card-header pt-6 pb-0">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">Customer</h3>
        </div>
    </div>
    <div class="card-body pt-4">
        <div class="d-flex flex-column gap-3 fs-7">
            <div>
                <div class="text-muted fs-8 text-uppercase mb-1">Name</div>
                <div class="fw-semibold text-gray-900">{{ $customerName }}</div>
            </div>
            @if (filled($customerPhone))
                <div>
                    <div class="text-muted fs-8 text-uppercase mb-1">Phone</div>
                    <div class="fw-semibold text-gray-900 user-select-all">{{ $customerPhone }}</div>
                </div>
            @endif
            @if (filled($customerEmail))
                <div>
                    <div class="text-muted fs-8 text-uppercase mb-1">Email</div>
                    <div class="fw-semibold text-gray-900 user-select-all">{{ $customerEmail }}</div>
                </div>
            @endif
            <div>
                <div class="text-muted fs-8 text-uppercase mb-1">Assigned barista</div>
                <div class="text-gray-700">{{ $order->assignedBarista?->name ?: 'Not assigned yet' }}</div>
            </div>
            @if (filled($order->customer_notes))
                <div>
                    <div class="text-muted fs-8 text-uppercase mb-1">Customer notes</div>
                    <div class="text-gray-700">{{ $order->customer_notes }}</div>
                </div>
            @endif
        </div>
    </div>
</div>
