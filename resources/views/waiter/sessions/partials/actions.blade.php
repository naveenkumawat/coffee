@if ($session->allowsNewRounds())
    <form method="POST" action="{{ route('waiter.sessions.request-bill', $session) }}">
        @csrf
        <x-internal.button label="Request bill" type="submit" variant="default" icon="ki-bill" />
    </form>
@endif
<form method="POST" action="{{ route('waiter.sessions.cash.receive', $session) }}">
    @csrf
    <x-internal.button label="Mark cash received" type="submit" variant="success" icon="ki-check" />
</form>
<form method="POST" action="{{ route('waiter.sessions.reopen', $session) }}">
    @csrf
    <x-internal.button label="Reopen" type="submit" variant="default" icon="ki-arrows-circle" />
</form>
<form method="POST" action="{{ route('waiter.sessions.close', $session) }}">
    @csrf
    <x-internal.button label="Close session" type="submit" variant="dark" icon="ki-check-circle" />
</form>
