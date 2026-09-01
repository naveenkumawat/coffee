@extends('internal.layouts.default', ['panel' => 'waiter'])

@section('title', 'Session '.$session->session_number)

@section('content')
    <div class="card card-flush mb-5">
        <div class="card-body">
            <div class="fw-bold fs-3">{{ $session->session_number }} · {{ $session->tableDisplayLabel() }}</div>
            <div class="text-muted mb-4">{{ $session->status?->label() }}</div>
            <div>Running total: <strong>{{ $bill['total'] }}</strong></div>
        </div>
    </div>

    @if ($session->allowsNewRounds())
        <div class="card card-flush mb-5">
            <div class="card-header"><h3 class="card-title">Add round</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('waiter.sessions.rounds.store', $session) }}" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <select name="product_variant_id" class="form-select" required>
                            @foreach ($variants as $variant)
                                <option value="{{ $variant->getKey() }}">{{ $variant->product?->name }} — {{ $variant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="quantity" value="1" min="1" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" type="submit">Place round</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card card-flush mb-5">
        <div class="card-header"><h3 class="card-title">Rounds</h3></div>
        <div class="card-body">
            @forelse ($session->orders as $order)
                <div class="mb-4">
                    <div class="fw-bold">Round {{ $order->dining_round_number }} · {{ $order->order_number }} · {{ $order->status?->label() }}</div>
                    <ul>
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

    <div class="d-flex flex-wrap gap-2">
        @if ($session->allowsNewRounds())
            <form method="POST" action="{{ route('waiter.sessions.request-bill', $session) }}">@csrf<button class="btn btn-warning">Request bill</button></form>
        @endif
        <form method="POST" action="{{ route('waiter.sessions.cash.receive', $session) }}">@csrf<button class="btn btn-success">Mark cash received</button></form>
        <form method="POST" action="{{ route('waiter.sessions.reopen', $session) }}">@csrf<button class="btn btn-light">Reopen</button></form>
        <form method="POST" action="{{ route('waiter.sessions.close', $session) }}">@csrf<button class="btn btn-dark">Close session</button></form>
        <a class="btn btn-light-primary" href="{{ route('waiter.sessions.invoice', $session) }}">Invoice PDF</a>
    </div>
@endsection
