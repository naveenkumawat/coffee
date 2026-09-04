@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentStatus;

    $isCash = $order->isCashPayment();
    $isManualUpi = $order->payment_method === PaymentMethod::Manual;
    $proofShowRoute = $paymentProofShowRoute ?? 'administrator.orders.payment-proof.show';
    $rejectRoute = $paymentProofRejectRoute ?? 'administrator.orders.payment-proof.reject';
    $verifyRoute = $paymentVerifyRoute ?? 'administrator.orders.status.update';
    $proofUrl = (! $isCash && $order->hasPaymentProof())
        ? route($proofShowRoute, $order)
        : null;
    $cashReceiveUrl = $markCashRoute ?? null;
    $canVerifyManual = ($showAdminActions ?? false)
        && $isManualUpi
        && $order->status === OrderStatus::PendingPayment
        && $order->hasManualPaymentEvidence()
        && $order->payment_status !== PaymentStatus::Rejected
        && $order->payment_status !== PaymentStatus::Confirmed;
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
                    <div class="fw-semibold text-gray-900">
                        {{ $order->payment_method?->label() ?? 'Manual UPI / QR Payment' }}
                    </div>
                </div>
                <div class="col-6">
                    <div class="text-muted fs-8 mb-1 text-uppercase">Status</div>
                    <span class="badge {{ $order->payment_status?->badgeClass() ?? 'badge-light' }}">
                        @if ($isCash && $order->payment_status === PaymentStatus::Confirmed)
                            Received
                        @elseif ($isCash)
                            Pending
                        @else
                            {{ $order->payment_status?->label() ?? 'Pending' }}
                        @endif
                    </span>
                </div>
                @if ($showFinancialSummary ?? true)
                    <div class="col-6">
                        <div class="text-muted fs-8 mb-1 text-uppercase">Amount expected</div>
                        <div class="fw-bold text-gray-900">Rs {{ number_format((float) $order->total_amount, 2) }}</div>
                    </div>
                @endif
                @if ($order->payment_confirmed_at)
                    <div class="col-6">
                        <div class="text-muted fs-8 mb-1 text-uppercase">{{ $isCash ? 'Received' : 'Confirmed' }}</div>
                        <div class="text-gray-700 fs-7">{{ $order->payment_confirmed_at->format('d M Y, h:i A') }}</div>
                    </div>
                @endif
                @if ($order->paymentReceivedBy)
                    <div class="col-6">
                        <div class="text-muted fs-8 mb-1 text-uppercase">{{ $isCash ? 'Received by' : 'Verified by' }}</div>
                        <div class="text-gray-700 fs-7">{{ $order->paymentReceivedBy->name }}</div>
                    </div>
                @endif
            </div>

            @if ($order->payment_reference && ! $order->hasPaymentTransactionId())
                <div>
                    <div class="text-muted fs-8 mb-1 text-uppercase">Reference</div>
                    <div class="text-gray-700 user-select-all">{{ $order->payment_reference }}</div>
                </div>
            @endif

            @if ($isCash)
                @if ($order->canMarkCashReceived() && filled($cashReceiveUrl))
                    <form method="POST" action="{{ $cashReceiveUrl }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">Mark Cash Received</button>
                    </form>
                @elseif ($order->payment_status === PaymentStatus::Confirmed)
                    <div class="text-muted fs-7">Cash received — no UPI Transaction ID required.</div>
                @else
                    <div class="text-muted fs-7">Cash pending — collect at {{ $order->isTakeaway() ? 'pickup' : 'the cafe' }}.</div>
                @endif
            @elseif ($isManualUpi)
                <div>
                    <div class="text-muted fs-8 mb-1 text-uppercase">Transaction ID / UTR</div>
                    @if ($order->hasPaymentTransactionId())
                        <div class="fw-semibold text-gray-900 user-select-all fs-5">{{ $order->payment_transaction_id }}</div>
                        <div class="text-muted fs-8 mt-1">
                            Submitted: {{ $order->payment_proof_uploaded_at?->format('d M Y, h:i A') ?: '—' }}
                        </div>
                    @else
                        <div class="text-muted fs-7">No Transaction ID submitted yet.</div>
                    @endif
                </div>

                @if ($proofUrl)
                    <div>
                        <div class="text-muted fs-8 mb-2 text-uppercase">Historical screenshot</div>
                        <a
                            href="{{ $proofUrl }}"
                            target="_blank"
                            rel="noopener"
                            class="payment-proof-thumb-link d-inline-block"
                            title="Open historical payment screenshot"
                        >
                            <img
                                src="{{ $proofUrl }}"
                                alt="Historical payment screenshot for {{ $order->order_number }}"
                                class="payment-proof-thumb"
                            />
                        </a>
                    </div>
                @endif

                @if ($order->payment_proof_rejection_notes)
                    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4">
                        <div>
                            <div class="fw-bold text-gray-900 mb-1">Not verified</div>
                            <div class="text-gray-700">{{ $order->payment_proof_rejection_notes }}</div>
                        </div>
                    </div>
                @endif

                @if ($canVerifyManual)
                    <div class="d-flex flex-wrap gap-2">
                        <form
                            method="POST"
                            action="{{ route($verifyRoute, $order) }}"
                            data-confirm-title="Verify Payment?"
                            data-confirm-body="Confirm this Manual UPI payment has been received for this order."
                            data-confirm-label="Verify payment"
                            data-confirm-class="btn-success"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ OrderStatus::PaymentConfirmed->value }}" />
                            <input type="hidden" name="notes" value="Manual UPI Transaction ID verified." />
                            <button type="submit" class="btn btn-sm btn-success">Verify Payment</button>
                        </form>
                        <form
                            method="POST"
                            action="{{ route($rejectRoute, $order) }}"
                            class="flex-grow-1"
                            data-confirm-title="Reject payment?"
                            data-confirm-body="This marks the submitted payment proof as not found / not verified."
                            data-confirm-label="Reject payment"
                            data-confirm-class="btn-danger"
                            data-confirm-require-reason="1"
                            data-confirm-reason-field="notes"
                            data-confirm-reason-label="Rejection reason"
                        >
                            @csrf
                            <div class="d-flex flex-column flex-sm-row gap-2">
                                <input
                                    type="text"
                                    name="notes"
                                    class="form-control form-control-sm"
                                    placeholder="Reject / not found reason (required)"
                                    value="{{ old('notes') }}"
                                    maxlength="500"
                                />
                                <button type="submit" class="btn btn-sm btn-light-warning text-nowrap">Reject / Not Found</button>
                            </div>
                        </form>
                    </div>
                @endif
            @else
                <div class="text-muted fs-7">
                    Online gateway payment — confirmation is server-verified via the payment provider.
                </div>
            @endif
        </div>
    </div>
</div>
