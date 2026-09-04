@php
    use App\Enums\DiningSessionStatus;
    use App\Enums\PaymentStatus;

    $paymentConfirmed = $session->payment_status === PaymentStatus::Confirmed
        || $session->status === DiningSessionStatus::Paid;
    $canResumeOrdering = in_array($session->status, [
        DiningSessionStatus::BillingRequested,
        DiningSessionStatus::AwaitingPayment,
    ], true) && ! $paymentConfirmed;
    $tableLabel = $session->tableDisplayLabel();
@endphp

@if ($session->allowsNewRounds())
    <form
        method="POST"
        action="{{ route('waiter.sessions.request-bill', $session) }}"
        data-confirm-title="Request the bill?"
        data-confirm-body="Once the bill is requested, guests won't be able to add more orders to this dining session."
        data-confirm-label="Request bill"
    >
        @csrf
        <x-internal.button label="Request bill" type="submit" variant="default" icon="ki-bill" />
    </form>
@endif
@if ($session->hasFinalizedBill() && $session->payment_status?->value !== 'confirmed')
    <form method="POST" action="{{ route('waiter.sessions.payment-method', $session) }}" class="d-inline-flex gap-2">
        @csrf
        <select name="payment_method" class="form-select form-select-sm" style="width: auto;">
            <option value="cash" @selected($session->payment_method?->value === 'cash')>Cash</option>
            <option value="manual_upi" @selected($session->payment_method?->value === 'manual')>UPI</option>
        </select>
        <x-internal.button label="Set method" type="submit" variant="default" icon="ki-setting-2" />
    </form>
    @if ($session->payment_method === null || $session->payment_method?->value === 'cash')
        <form
            method="POST"
            action="{{ route('waiter.sessions.cash.receive', $session) }}"
            data-confirm-title="Mark cash received?"
            data-confirm-body="Confirm cash has been collected for Table {{ $tableLabel }}."
            data-confirm-label="Mark cash received"
            data-confirm-class="btn-success"
        >
            @csrf
            <x-internal.button label="Mark cash received" type="submit" variant="success" icon="ki-check" />
        </form>
    @endif
@endif
@if ($canResumeOrdering)
    <form
        method="POST"
        action="{{ route('waiter.sessions.reopen', $session) }}"
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
@endif
@if ($paymentConfirmed && $session->status !== DiningSessionStatus::Closed)
    <form
        method="POST"
        action="{{ route('waiter.sessions.close', $session) }}"
        data-confirm-title="Close dining session?"
        data-confirm-body="This will end the session and release Table {{ $tableLabel }}. No more orders can be added."
        data-confirm-label="Close session"
    >
        @csrf
        <x-internal.button label="Close session" type="submit" variant="dark" icon="ki-check-circle" />
    </form>
@endif
