@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;
@endphp

<div class="card card-flush internal-card mb-7">
    <div class="card-header pt-7">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">Payment</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="d-flex flex-column gap-4">
            <div>
                <div class="text-muted fs-7 mb-1">Method</div>
                <div class="fw-bold text-gray-900">{{ $order->payment_method?->label() ?? 'Manual' }}</div>
            </div>
            <div>
                <div class="text-muted fs-7 mb-1">Payment status</div>
                <div class="fw-bold text-gray-900">{{ $order->payment_status?->label() ?? 'Pending' }}</div>
            </div>
            @if ($order->payment_reference)
                <div>
                    <div class="text-muted fs-7 mb-1">Reference</div>
                    <div class="text-gray-700">{{ $order->payment_reference }}</div>
                </div>
            @endif
            @if ($order->hasPaymentProof())
                <div>
                    <div class="text-muted fs-7 mb-1">Uploaded proof</div>
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <a href="{{ route('administrator.orders.payment-proof.show', $order) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light-primary">
                            View screenshot
                        </a>
                        <span class="text-muted fs-8">
                            {{ $order->payment_proof_uploaded_at?->format('d M Y, h:i A') }}
                            @if ($order->payment_proof_mime)
                                · {{ $order->payment_proof_mime }}
                            @endif
                        </span>
                    </div>
                </div>
            @else
                <div class="text-muted">No payment screenshot uploaded yet.</div>
            @endif
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
                    <label for="payment_proof_notes" class="form-label">Request replacement</label>
                    <textarea id="payment_proof_notes" name="notes" rows="2" class="form-control mb-3" placeholder="Optional note for the customer">{{ old('notes') }}</textarea>
                    <button type="submit" class="btn btn-sm btn-light-warning">Ask customer to re-upload</button>
                </form>
            @endif
        </div>
    </div>
</div>
