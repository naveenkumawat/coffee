@php
    use App\Enums\OrderPreparationStatus;

    $ticket = $ticket;
    $canTransition = $canTransition ?? false;
    $acceptRouteName = $acceptRouteName ?? null;
    $preparingRouteName = $preparingRouteName ?? null;
    $readyRouteName = $readyRouteName ?? null;
    $orderShowRouteName = $orderShowRouteName ?? null;
    $order = $ticket->order;
    $items = $ticket->items();
    $tableLabel = $order?->diningSession?->tableDisplayLabel()
        ?? $order?->tableDisplayLabel();
@endphp

<div class="border border-gray-200 rounded-3 p-4 bg-light">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="fw-bold text-gray-900">{{ $order?->order_number ?: 'Order #'.$ticket->order_id }}</div>
            @if ($tableLabel)
                <div class="text-muted fs-8">{{ $tableLabel }}@if($order?->dining_round_number) · Round {{ $order->dining_round_number }}@endif</div>
            @endif
            <div class="mt-1">
                <span class="badge {{ $ticket->status?->badgeClass() }}">{{ $ticket->status?->label() }}</span>
                <span class="badge badge-light">{{ $ticket->station?->label() }}</span>
            </div>
        </div>
        @if ($orderShowRouteName && $order)
            <a href="{{ route($orderShowRouteName, $order) }}" class="btn btn-sm btn-light">Open</a>
        @endif
    </div>

    <ul class="list-unstyled mb-3">
        @forelse ($items as $item)
            <li class="fs-7 text-gray-800 mb-1">
                <span class="fw-bold">{{ $item->quantity }}×</span> {{ $item->product_name }}
                @if ($item->variant_name)
                    <span class="text-muted">({{ $item->variant_name }})</span>
                @endif
            </li>
        @empty
            <li class="fs-7 text-muted">No station items.</li>
        @endforelse
    </ul>

    @if (filled($order?->customer_notes))
        <div class="fs-8 text-muted mb-3">Notes: {{ $order->customer_notes }}</div>
    @endif

    <div class="fs-8 text-muted mb-3">
        Created {{ $ticket->created_at?->diffForHumans() }}
        @if ($ticket->accepted_at)
            · Accepted {{ $ticket->accepted_at->diffForHumans() }}
        @endif
        @if ($ticket->preparing_at)
            · Preparing {{ $ticket->preparing_at->diffForHumans() }}
        @endif
        @if ($ticket->ready_at)
            · Ready {{ $ticket->ready_at->diffForHumans() }}
        @endif
    </div>

    @if ($canTransition)
        <div class="d-flex flex-wrap gap-2">
            @if ($ticket->status === OrderPreparationStatus::Pending && $acceptRouteName)
                <form method="POST" action="{{ route($acceptRouteName, $ticket) }}">
                    @csrf
                    <x-internal.button label="Accept" type="submit" variant="info" />
                </form>
            @endif
            @if ($ticket->status === OrderPreparationStatus::Accepted && $preparingRouteName)
                <form method="POST" action="{{ route($preparingRouteName, $ticket) }}">
                    @csrf
                    <x-internal.button label="Start preparing" type="submit" variant="dark" />
                </form>
            @endif
            @if ($ticket->status === OrderPreparationStatus::Preparing && $readyRouteName)
                <form method="POST" action="{{ route($readyRouteName, $ticket) }}">
                    @csrf
                    <x-internal.button label="Mark ready" type="submit" variant="success" />
                </form>
            @endif
        </div>
    @endif
</div>
