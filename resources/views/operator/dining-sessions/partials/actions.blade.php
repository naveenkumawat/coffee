@php
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentStatus;

    $canChangeMethod = $session->hasFinalizedBill()
        && $session->payment_status !== PaymentStatus::Confirmed
        && $session->payment_status !== PaymentStatus::AwaitingReview
        && ! $session->hasManualPaymentEvidence();
@endphp

@if ($canChangeMethod)
    <form method="POST" action="{{ route('operator.dining-sessions.payment-method', $session) }}" class="d-inline-flex gap-2 align-items-center">
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
    <form method="POST" action="{{ route('operator.dining-sessions.cash.receive', $session) }}">
        @csrf
        <x-internal.button label="Mark cash received" type="submit" variant="success" icon="ki-check" />
    </form>
@endif

<form method="POST" action="{{ route('operator.dining-sessions.reopen', $session) }}">
    @csrf
    <input type="hidden" name="note" value="Reopened from operator panel.">
    <x-internal.button label="Reopen" type="submit" variant="default" icon="ki-arrows-circle" />
</form>
<form method="POST" action="{{ route('operator.dining-sessions.close', $session) }}">
    @csrf
    <x-internal.button label="Close" type="submit" variant="dark" icon="ki-check-circle" />
</form>
