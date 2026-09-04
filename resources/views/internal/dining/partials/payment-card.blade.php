@php
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentStatus;

    $prefix = $routePrefix ?? 'administrator';
    $isManualUpi = $session->payment_method === PaymentMethod::Manual;
    $isCash = $session->isCashPayment();
    $hasEvidence = $session->hasManualPaymentEvidence();
    $awaitingReview = $session->payment_status === PaymentStatus::AwaitingReview;
    $rejected = $session->payment_status === PaymentStatus::Rejected;
    $confirmed = $session->payment_status === PaymentStatus::Confirmed;
    $canVerify = $isManualUpi && $awaitingReview && $hasEvidence;
    $amount = number_format((float) ($session->total_amount ?? 0), 2, '.', '');
@endphp

@if ($session->hasFinalizedBill() && ($isManualUpi || $isCash || $confirmed))
    <div class="card card-flush internal-card mb-7">
        <div class="card-header pt-6 pb-0">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Payment</h3>
            </div>
        </div>
        <div class="card-body pt-5">
            <div class="d-flex flex-column gap-4">
                <div class="row g-4">
                    <div class="col-sm-6 col-md-3">
                        <div class="text-muted fs-8 mb-1 text-uppercase">Method</div>
                        <div class="fw-semibold text-gray-900">{{ $session->payment_method?->label() ?: '—' }}</div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="text-muted fs-8 mb-1 text-uppercase">Status</div>
                        <x-internal.payment-status-badge :status="$session->payment_status" />
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="text-muted fs-8 mb-1 text-uppercase">Amount</div>
                        <div class="fw-bold text-gray-900">Rs {{ $amount }}</div>
                    </div>
                    @if ($session->paid_at)
                        <div class="col-sm-6 col-md-3">
                            <div class="text-muted fs-8 mb-1 text-uppercase">{{ $isCash ? 'Received' : 'Confirmed' }}</div>
                            <div class="text-gray-700 fs-7">
                                {{ $session->paid_at->timezone(config('app.timezone'))->format('d M Y, h:i A') }}
                            </div>
                        </div>
                    @endif
                    @if ($session->paymentReceivedBy)
                        <div class="col-sm-6 col-md-3">
                            <div class="text-muted fs-8 mb-1 text-uppercase">{{ $isCash ? 'Received by' : 'Verified by' }}</div>
                            <div class="text-gray-700 fs-7">{{ $session->paymentReceivedBy->name }}</div>
                        </div>
                    @endif
                </div>

                @if ($isManualUpi)
                    <div>
                        <div class="text-muted fs-8 mb-1 text-uppercase">Transaction ID / UTR</div>
                        @if (filled($session->payment_reference))
                            <div class="fw-semibold text-gray-900 user-select-all fs-5">{{ $session->payment_reference }}</div>
                            <div class="text-muted fs-8 mt-1">
                                Submitted: {{ $session->payment_proof_uploaded_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?: '—' }}
                            </div>
                        @else
                            <div class="text-muted fs-7">No Transaction ID submitted yet.</div>
                        @endif
                    </div>

                    @if ($session->hasPaymentProof())
                        <div>
                            <div class="text-muted fs-8 mb-2 text-uppercase">Historical screenshot</div>
                            <x-internal.button
                                label="View screenshot"
                                :url="route($prefix.'.dining-sessions.payment-proof.show', $session)"
                                variant="default"
                                icon="ki-eye"
                                target="_blank"
                            />
                        </div>
                    @endif

                    @if ($rejected && filled($session->payment_proof_rejection_notes))
                        <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4">
                            <div>
                                <div class="fw-bold text-gray-900 mb-1">Not verified</div>
                                <div class="text-gray-700">{{ $session->payment_proof_rejection_notes }}</div>
                            </div>
                        </div>
                    @endif

                    @if ($canVerify)
                        <div class="d-flex flex-column flex-lg-row gap-3">
                            <form method="POST" action="{{ route($prefix.'.dining-sessions.payment.confirm', $session) }}">
                                @csrf
                                <x-internal.button label="Verify Payment" type="submit" variant="success" icon="ki-check" />
                            </form>
                            <form method="POST" action="{{ route($prefix.'.dining-sessions.payment-proof.reject', $session) }}" class="flex-grow-1">
                                @csrf
                                <div class="d-flex flex-column flex-sm-row gap-2">
                                    <input
                                        type="text"
                                        name="notes"
                                        class="form-control form-control-sm"
                                        placeholder="Reject / not found reason (required)"
                                        value="{{ old('notes') }}"
                                        required
                                        maxlength="500"
                                    />
                                    <x-internal.button label="Reject / Not Found" type="submit" variant="danger" icon="ki-cross" />
                                </div>
                            </form>
                        </div>
                    @elseif ($awaitingReview === false && $confirmed === false && ! $hasEvidence)
                        <div class="text-muted fs-7">Waiting for the customer to submit a Transaction ID / UTR.</div>
                    @endif
                @elseif ($isCash && ! $confirmed)
                    <div class="text-muted fs-7">Cash pending — collect at the table, then mark cash received.</div>
                @endif
            </div>
        </div>
    </div>
@endif
