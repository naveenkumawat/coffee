<form method="POST" action="{{ route('operator.dining-sessions.reopen', $session) }}">
    @csrf
    <input type="hidden" name="note" value="Reopened from operator panel.">
    <x-internal.button label="Reopen" type="submit" variant="default" icon="ki-arrows-circle" />
</form>
<form method="POST" action="{{ route('operator.dining-sessions.close', $session) }}">
    @csrf
    <x-internal.button label="Close" type="submit" variant="dark" icon="ki-check-circle" />
</form>
