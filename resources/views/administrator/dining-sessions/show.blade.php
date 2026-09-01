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

@section('content')
    <div class="card card-flush internal-card mb-7">
        <div class="card-body">
            <div class="fw-bold fs-3 mb-2">{{ $session->session_number }} · {{ $session->tableDisplayLabel() }}</div>
            <div class="text-muted mb-4">{{ $session->status?->label() }}</div>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="text-muted fs-8">Customer</div>
                    <div>{{ $session->customer_name_snapshot ?: 'Walk-in' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted fs-8">Opened by</div>
                    <div>{{ $session->openedBy?->name ?: '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted fs-8">Running / bill total</div>
                    <div>{{ $bill['total'] ?? ($session->total_amount ?: '0.00') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted fs-8">Payment</div>
                    <div>{{ $session->payment_method?->label() ?: '—' }} / {{ $session->payment_status?->label() ?: '—' }}</div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-6">
                <a class="btn btn-light-primary" href="{{ route('administrator.dining-sessions.invoice', $session) }}">Invoice PDF</a>
                <form method="POST" action="{{ route('administrator.dining-sessions.reopen', $session) }}">
                    @csrf
                    <input type="hidden" name="note" value="Reopened from administrator panel.">
                    <button class="btn btn-warning" type="submit">Reopen</button>
                </form>
                <form method="POST" action="{{ route('administrator.dining-sessions.close', $session) }}">
                    @csrf
                    <button class="btn btn-dark" type="submit">Close</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-header">
            <h3 class="card-title">Rounds</h3>
        </div>
        <div class="card-body">
            @forelse ($session->orders as $order)
                <div class="mb-5">
                    <div class="fw-bold">Round {{ $order->dining_round_number }} · {{ $order->order_number }} · {{ $order->status?->label() }}</div>
                    <ul class="mb-0">
                        @foreach ($order->items as $item)
                            <li>{{ $item->quantity }} × {{ $item->product_name }} @if($item->variant_name)({{ $item->variant_name }})@endif</li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="text-muted">No rounds yet.</div>
            @endforelse
        </div>
    </div>
@endsection
