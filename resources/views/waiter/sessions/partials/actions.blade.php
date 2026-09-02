@if ($session->allowsNewRounds())
    <form method="POST" action="{{ route('waiter.sessions.request-bill', $session) }}">
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
        <form method="POST" action="{{ route('waiter.sessions.cash.receive', $session) }}">
            @csrf
            <x-internal.button label="Mark cash received" type="submit" variant="success" icon="ki-check" />
        </form>
    @endif
@endif
<form method="POST" action="{{ route('waiter.sessions.reopen', $session) }}">
    @csrf
    <x-internal.button label="Reopen" type="submit" variant="default" icon="ki-arrows-circle" />
</form>
<form method="POST" action="{{ route('waiter.sessions.close', $session) }}">
    @csrf
    <x-internal.button label="Close session" type="submit" variant="dark" icon="ki-check-circle" />
</form>
