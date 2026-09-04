@php
    use App\Enums\DiningSessionStatus;
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentStatus;

    $paymentConfirmed = $session->payment_status === PaymentStatus::Confirmed
        || $session->status === DiningSessionStatus::Paid;
    $canChangeMethod = $session->hasFinalizedBill()
        && $session->payment_status !== PaymentStatus::Confirmed
        && $session->payment_status !== PaymentStatus::AwaitingReview
        && ! $session->hasManualPaymentEvidence();
    $canResumeOrdering = in_array($session->status, [
        DiningSessionStatus::BillingRequested,
        DiningSessionStatus::AwaitingPayment,
    ], true) && ! $paymentConfirmed;
    $canReopenClosed = $session->status === DiningSessionStatus::Closed && ! $paymentConfirmed;
    $tableLabel = $session->tableDisplayLabel();
@endphp

@if ($canChangeMethod)
    <form method="POST" action="{{ route('administrator.dining-sessions.payment-method', $session) }}" class="d-inline-flex gap-2 align-items-center">
        @csrf
        <select name="payment_method" class="form-select form-select-sm" style="width: auto;">
            <option value="cash" @selected($session->payment_method === PaymentMethod::Cash)>Cash</option>
            <option value="manual_upi" @selected($session->payment_method === PaymentMethod::Manual)>Manual UPI</option>
        </select>
        <x-internal.button label="Set method" type="submit" variant="default" icon="ki-setting-2" />
    </form>
@endif

@if ($session->hasFinalizedBill()
    && ($session->payment_method === null || $session->payment_method === PaymentMethod::Cash)
    && $session->payment_status !== PaymentStatus::Confirmed)
    <form
        method="POST"
        action="{{ route('administrator.dining-sessions.cash.receive', $session) }}"
        data-confirm-title="Mark cash received?"
        data-confirm-body="Confirm cash has been collected for Table {{ $tableLabel }}."
        data-confirm-label="Mark cash received"
        data-confirm-class="btn-success"
    >
        @csrf
        <x-internal.button label="Mark cash received" type="submit" variant="success" icon="ki-check" />
    </form>
@endif

@if ($canResumeOrdering)
    <form
        method="POST"
        action="{{ route('administrator.dining-sessions.reopen', $session) }}"
        data-confirm-title="Resume ordering?"
        data-confirm-body="This reopens the unpaid bill for Table {{ $tableLabel }} so more orders can be added."
        data-confirm-label="Resume ordering"
        data-confirm-require-reason="1"
        data-confirm-reason-field="note"
        data-confirm-reason-label="Reason"
    >
        @csrf
        <x-internal.button label="Resume ordering" type="submit" variant="default" icon="ki-arrows-circle" />
    </form>
@elseif ($canReopenClosed)
    <form
        method="POST"
        action="{{ route('administrator.dining-sessions.reopen', $session) }}"
        data-confirm-title="Reopen dining session?"
        data-confirm-body="This will make the session active again and may occupy Table {{ $tableLabel }}."
        data-confirm-label="Reopen session"
        data-confirm-require-reason="1"
        data-confirm-reason-field="note"
        data-confirm-reason-label="Reason"
    >
        @csrf
        <x-internal.button label="Reopen session" type="submit" variant="default" icon="ki-arrows-circle" />
    </form>
@endif

@if ($session->status !== DiningSessionStatus::Closed)
    <form
        method="POST"
        action="{{ route('administrator.dining-sessions.close', $session) }}"
        data-confirm-title="Close dining session?"
        data-confirm-body="This will end the session and release Table {{ $tableLabel }}. No more orders can be added."
        data-confirm-label="Close session"
        data-confirm-class="btn-danger"
        @if (! $paymentConfirmed)
            data-confirm-require-reason="1"
            data-confirm-reason-field="reason"
            data-confirm-reason-label="Reason"
        @endif
    >
        @csrf
        <x-internal.button label="Close session" type="submit" variant="danger" icon="ki-check-circle" />
    </form>
@endif
