@extends('administrator.layouts.default')

@section('page-title', 'Dining Session '.$session->session_number)

@section('page-description', 'Intervene on table-service payment and session lifecycle.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Dining Sessions', 'url' => route('administrator.dining-sessions.index')],
        ['label' => $session->session_number],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.dining-sessions.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
@endsection

@section('content')
    @include('internal.dining.partials.session-header', [
        'session' => $session,
        'bill' => $bill,
        'showAdminMeta' => true,
        'invoiceRoute' => route('administrator.dining-sessions.invoice', $session),
        'actionsView' => 'administrator.dining-sessions.partials.actions',
        'paymentCardView' => 'internal.dining.partials.payment-card',
        'routePrefix' => 'administrator',
    ])

    @include('internal.dining.partials.rounds-list', ['session' => $session])
@endsection
