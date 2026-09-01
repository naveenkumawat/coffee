<form method="POST" action="{{ route('administrator.dining-sessions.reopen', $session) }}">
    @csrf
    <input type="hidden" name="note" value="Reopened from administrator panel.">
    <x-internal.button label="Reopen" type="submit" variant="default" icon="ki-arrows-circle" />
</form>
<form method="POST" action="{{ route('administrator.dining-sessions.close', $session) }}">
    @csrf
    <x-internal.button label="Close" type="submit" variant="dark" icon="ki-check-circle" />
</form>
