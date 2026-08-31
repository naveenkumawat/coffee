@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;
@endphp

@php
    $proofUrl = $order->hasPaymentProof()
        ? route('administrator.orders.payment-proof.show', $order)
        : null;
@endphp

<div class="card card-flush internal-card mb-5">
    <div class="card-header pt-6 pb-0">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">Payment</h3>
        </div>
    </div>
    <div class="card-body pt-5">
        <div class="d-flex flex-column gap-4">
            <div class="row g-4">
                <div class="col-6">
                    <div class="text-muted fs-8 mb-1 text-uppercase">Method</div>
                    <div class="fw-semibold text-gray-900">{{ $order->payment_method?->label() ?? 'Manual' }}</div>
                </div>
                <div class="col-6">
                    <div class="text-muted fs-8 mb-1 text-uppercase">Status</div>
                    <span class="badge {{ $order->payment_status?->badgeClass() ?? 'badge-light' }}">
                        {{ $order->payment_status?->label() ?? 'Pending' }}
                    </span>
                </div>
                <div class="col-6">
                    <div class="text-muted fs-8 mb-1 text-uppercase">Order total</div>
                    <div class="fw-bold text-gray-900">Rs {{ number_format((float) $order->total_amount, 2) }}</div>
                </div>
                @if ($order->payment_confirmed_at)
                    <div class="col-6">
                        <div class="text-muted fs-8 mb-1 text-uppercase">Confirmed</div>
                        <div class="text-gray-700 fs-7">{{ $order->payment_confirmed_at->format('d M Y, h:i A') }}</div>
                    </div>
                @endif
            </div>

            @if ($order->payment_reference)
                <div>
                    <div class="text-muted fs-8 mb-1 text-uppercase">Reference</div>
                    <div class="text-gray-700 user-select-all">{{ $order->payment_reference }}</div>
                </div>
            @endif

            <div>
                <div class="text-muted fs-8 mb-2 text-uppercase">Payment proof</div>

                @if ($proofUrl)
                    <a
                        href="{{ $proofUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="payment-proof-thumb-link d-inline-block"
                        title="Open full payment screenshot"
                    >
                        <img
                            src="{{ $proofUrl }}"
                            alt="Payment proof screenshot for {{ $order->order_number }}"
                            class="payment-proof-thumb"
                        />
                    </a>
                    <div class="text-muted fs-8 mt-2">
                        Submitted: {{ $order->payment_proof_uploaded_at?->format('d M Y, h:i A') ?: '—' }}
                    </div>
                @else
                    <div class="text-muted fs-7">No payment proof submitted.</div>
                @endif
            </div>

            @if ($order->payment_proof_rejection_notes)
                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4">
                    <div>
                        <div class="fw-bold text-gray-900 mb-1">Replacement requested</div>
                        <div class="text-gray-700">{{ $order->payment_proof_rejection_notes }}</div>
                    </div>
                </div>
            @endif

            @if (($showAdminActions ?? false) && $order->status === OrderStatus::PendingPayment && $order->hasPaymentProof() && $order->payment_status !== PaymentStatus::Rejected)
                <form method="POST" action="{{ route('administrator.orders.payment-proof.reject', $order) }}" class="border border-gray-300 rounded p-4">
                    @csrf
                    <label for="payment_proof_notes" class="form-label fs-7">Request replacement</label>
                    <textarea id="payment_proof_notes" name="notes" rows="2" class="form-control form-control-sm mb-3" placeholder="Optional note for the customer">{{ old('notes') }}</textarea>
                    <button type="submit" class="btn btn-sm btn-light-warning">Ask customer to re-upload</button>
                </form>
            @endif
        </div>
    </div>
</div>
