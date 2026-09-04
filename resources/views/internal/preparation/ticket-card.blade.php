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
                @if ($item->relationLoaded('addOns') ? $item->addOns->isNotEmpty() : $item->addOns()->exists())
                    <div class="text-muted fs-8 ms-4">
                        @foreach ($item->addOns as $addOn)
                            + {{ $addOn->name }} ×{{ $addOn->quantity }} each
                        @endforeach
                    </div>
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
        @php
            $queueAgeSeconds = $ticket->created_at ? $ticket->created_at->diffInSeconds(now()) : null;
            $stageStart = match ($ticket->status?->value) {
                'accepted' => $ticket->accepted_at ?? $ticket->created_at,
                'preparing' => $ticket->preparing_at ?? $ticket->accepted_at ?? $ticket->created_at,
                default => $ticket->created_at,
            };
            $stageElapsedSeconds = $stageStart ? $stageStart->diffInSeconds(now()) : null;
            $fmtLive = function (?int $seconds): string {
                if ($seconds === null) {
                    return '—';
                }
                $m = intdiv($seconds, 60);
                $s = $seconds % 60;

                return $m > 0 ? sprintf('%dm %02ds', $m, $s) : sprintf('%ds', $s);
            };
        @endphp
        Queue age {{ $fmtLive($queueAgeSeconds) }}
        @if (! in_array($ticket->status?->value, ['ready', 'cancelled'], true))
            · Stage {{ $fmtLive($stageElapsedSeconds) }}
        @endif
        · Created {{ $ticket->created_at?->diffForHumans() }}
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
                <form
                    method="POST"
                    action="{{ route($acceptRouteName, $ticket) }}"
                    data-confirm-title="Accept ticket?"
                    data-confirm-body="Accepting starts preparation for this station ticket."
                    data-confirm-label="Accept"
                    data-confirm-class="btn-info"
                >
                    @csrf
                    <x-internal.button label="Accept" type="submit" variant="info" />
                </form>
            @endif
            @if ($ticket->status === OrderPreparationStatus::Accepted && $preparingRouteName)
                <form
                    method="POST"
                    action="{{ route($preparingRouteName, $ticket) }}"
                    data-confirm-title="Start preparing?"
                    data-confirm-body="Mark this station ticket as actively preparing."
                    data-confirm-label="Start preparing"
                    data-confirm-class="btn-dark"
                >
                    @csrf
                    <x-internal.button label="Start preparing" type="submit" variant="dark" />
                </form>
            @endif
            @if ($ticket->status === OrderPreparationStatus::Preparing && $readyRouteName)
                <form
                    method="POST"
                    action="{{ route($readyRouteName, $ticket) }}"
                    data-confirm-title="Mark as Ready?"
                    data-confirm-body="Confirm all items for this station are ready."
                    data-confirm-label="Mark ready"
                    data-confirm-class="btn-success"
                >
                    @csrf
                    <x-internal.button label="Mark ready" type="submit" variant="success" />
                </form>
            @endif
        </div>
    @endif
</div>
