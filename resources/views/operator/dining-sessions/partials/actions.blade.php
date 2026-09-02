@if ($session->hasFinalizedBill() && $session->payment_status?->value !== 'confirmed')
    <form method="POST" action="{{ route('operator.dining-sessions.payment-method', $session) }}" class="d-inline-flex gap-2">
        @csrf
        <select name="payment_method" class="form-select form-select-sm" style="width: auto;">
            <option value="cash" @selected($session->payment_method?->value === 'cash')>Cash</option>
            <option value="manual_upi" @selected($session->payment_method?->value === 'manual')>UPI</option>
        </select>
        <x-internal.button label="Set method" type="submit" variant="default" icon="ki-setting-2" />
    </form>
    @if ($session->hasPaymentProof())
        <x-internal.button label="View proof" :url="route('operator.dining-sessions.payment-proof.show', $session)" variant="default" icon="ki-eye" />
        <form method="POST" action="{{ route('operator.dining-sessions.payment-proof.reject', $session) }}">
            @csrf
            <x-internal.button label="Reject proof" type="submit" variant="danger" icon="ki-cross" />
        </form>
        <form method="POST" action="{{ route('operator.dining-sessions.payment.confirm', $session) }}">
            @csrf
            <x-internal.button label="Confirm UPI" type="submit" variant="success" icon="ki-check" />
        </form>
    @endif
    @if ($session->payment_method === null || $session->payment_method?->value === 'cash')
        <form method="POST" action="{{ route('operator.dining-sessions.cash.receive', $session) }}">
            @csrf
            <x-internal.button label="Mark cash received" type="submit" variant="success" icon="ki-check" />
        </form>
    @endif
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
