@extends('waiter.layouts.default')

@section('page-title', 'Session '.$session->session_number)

@section('page-description', 'Manage rounds, bill request, cash close, and dining invoice.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Waiter Panel', 'url' => route('waiter.dashboard')],
        ['label' => 'Sessions', 'url' => route('waiter.sessions.index')],
        ['label' => $session->session_number],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('waiter.sessions.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
@endsection

@section('content')
    @include('internal.dining.partials.session-header', [
        'session' => $session,
        'bill' => $bill,
        'showAdminMeta' => false,
        'invoiceRoute' => route('waiter.sessions.invoice', $session),
        'actionsView' => 'waiter.sessions.partials.actions',
    ])

    @if (($diningTiming['bill_requested_elapsed_seconds'] ?? null) !== null)
        <div class="alert alert-info d-flex align-items-center mb-7">
            <span class="fs-7">
                Bill requested elapsed:
                <strong>
                    @php
                        $billElapsed = abs((int) $diningTiming['bill_requested_elapsed_seconds']);
                        $bm = intdiv($billElapsed, 60);
                        $bs = $billElapsed % 60;
                    @endphp
                    {{ $bm > 0 ? sprintf('%dm %02ds', $bm, $bs) : sprintf('%ds', $bs) }}
                </strong>
            </span>
        </div>
    @endif

    @if ($session->allowsNewRounds())
        <div class="card card-flush internal-card mb-7">
            <div class="card-header pt-6">
                <div class="card-title">
                    <h3 class="fw-bold text-gray-900">Add round</h3>
                </div>
            </div>
            <div class="card-body pt-4">
                <form
                    method="POST"
                    action="{{ route('waiter.sessions.rounds.store', $session) }}"
                    class="row g-4 align-items-end"
                    data-confirm-title="Place round?"
                    data-confirm-body="This places the selected item as a new dining round for preparation."
                    data-confirm-label="Place round"
                    data-confirm-class="btn-success"
                >
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label" for="product_variant_id">Item</label>
                        <select id="product_variant_id" name="product_variant_id" class="form-select" required data-control="select2" data-placeholder="Select an item">
                            <option></option>
                            @foreach ($variants as $variant)
                                <option value="{{ $variant->getKey() }}">{{ $variant->product?->name }} — {{ $variant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="quantity">Qty</label>
                        <input id="quantity" type="number" name="quantity" value="1" min="1" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <x-internal.button label="Place round" type="submit" variant="success" icon="ki-plus" />
                    </div>
                </form>
            </div>
        </div>
    @endif

    @include('internal.dining.partials.rounds-list', ['session' => $session, 'diningTiming' => $diningTiming])
@endsection
