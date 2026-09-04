@extends('operator.layouts.default')

@section('page-title', 'Dining Session '.$session->session_number)

@section('page-description', 'Operate table-service payment and session lifecycle.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Operator Panel', 'url' => route('operator.dashboard')],
        ['label' => 'Dining Sessions', 'url' => route('operator.dining-sessions.index')],
        ['label' => $session->session_number],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('operator.dining-sessions.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
@endsection

@section('content')
    @include('internal.dining.partials.session-header', [
        'session' => $session,
        'bill' => $bill,
        'showAdminMeta' => true,
        'invoiceRoute' => route('operator.dining-sessions.invoice', $session),
        'actionsView' => 'operator.dining-sessions.partials.actions',
        'paymentCardView' => 'internal.dining.partials.payment-card',
        'routePrefix' => 'operator',
    ])

    @include('internal.dining.partials.rounds-list', ['session' => $session])
@endsection
