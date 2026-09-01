@php
    $showAdminMeta = $showAdminMeta ?? false;
    $invoiceRoute = $invoiceRoute ?? null;
    $showInvoice = $showInvoice ?? filled($invoiceRoute);
    $billTotal = $bill['total'] ?? ($session->total_amount ?: '0.00');
@endphp

<div class="card card-flush internal-card mb-7">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
            <div class="min-w-0">
                <div class="fw-bold fs-3 text-gray-900 text-break">
                    {{ $session->session_number }} · {{ $session->tableDisplayLabel() }}
                </div>
                <div class="mt-2">
                    <x-internal.dining-session-status-badge :status="$session->status" />
                </div>
            </div>
            @if ($showInvoice)
                <x-internal.invoice-dropdown :items="[
                    ['label' => 'Download PDF', 'url' => $invoiceRoute, 'icon' => 'ki-file-down'],
                ]" />
            @endif
        </div>

        <div class="row g-4">
            @if ($showAdminMeta)
                <div class="col-md-3">
                    <div class="text-muted fs-8">Customer</div>
                    <div>{{ $session->customer_name_snapshot ?: 'Walk-in' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted fs-8">Opened by</div>
                    <div>{{ $session->openedBy?->name ?: '—' }}</div>
                </div>
            @endif
            <div class="col-md-3">
                <div class="text-muted fs-8">Running / bill total</div>
                <div class="fw-bold">{{ $billTotal }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted fs-8">Payment</div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span>{{ $session->payment_method?->label() ?: '—' }}</span>
                    <x-internal.payment-status-badge :status="$session->payment_status" />
                </div>
            </div>
        </div>

        @isset($actions)
            <div class="d-flex flex-wrap gap-2 mt-6">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
