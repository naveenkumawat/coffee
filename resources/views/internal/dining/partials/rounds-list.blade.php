<div class="card card-flush internal-card {{ $class ?? '' }}">
    <div class="card-header pt-6">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">Rounds</h3>
        </div>
    </div>
    <div class="card-body pt-4">
        @php
            $timingByOrderId = collect($diningTiming['rounds'] ?? [])->keyBy('order_id');
            $fmt = function (?int $seconds): string {
                if ($seconds === null) {
                    return '—';
                }
                $seconds = abs($seconds);
                $m = intdiv($seconds, 60);
                $s = $seconds % 60;

                return $m > 0 ? sprintf('%dm %02ds', $m, $s) : sprintf('%ds', $s);
            };
        @endphp
        @forelse ($session->orders as $order)
            @php
                $tickets = $order->preparations ?? collect();
                $activeTickets = $tickets->filter(fn ($ticket) => $ticket->status?->value !== 'cancelled');
                $allReady = $activeTickets->isNotEmpty()
                    && $activeTickets->every(fn ($ticket) => $ticket->status?->value === 'ready');
                $roundTiming = $timingByOrderId->get($order->id);
            @endphp
            <div class="mb-6 pb-5 border-bottom border-gray-200">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="fw-bold text-gray-900">
                        Round {{ $order->dining_round_number }} · {{ $order->order_number }}
                    </span>
                    <x-internal.order-status-badge :status="$order->status" :order="$order" />
                    @if ($allReady)
                        <span class="badge badge-light-success">Ready to Serve</span>
                    @endif
                    @if ($roundTiming)
                        <span class="badge badge-light">Elapsed {{ $fmt($roundTiming['round_elapsed_seconds']) }}</span>
                        @if ($roundTiming['ready_to_serve_age_seconds'] !== null)
                            <span class="badge badge-light-info">Ready age {{ $fmt($roundTiming['ready_to_serve_age_seconds']) }}</span>
                        @endif
                    @endif
                </div>

                @if ($activeTickets->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach ($activeTickets as $ticket)
                            <span class="badge {{ $ticket->status?->badgeClass() ?? 'badge-light' }}">
                                {{ $ticket->station?->label() ?? 'Station' }} · {{ $ticket->status?->label() ?? '—' }}
                            </span>
                        @endforeach
                    </div>
                @endif

                <ul class="mb-0">
                    @foreach ($order->items as $item)
                        <li>
                            {{ $item->quantity }} × {{ $item->product_name }}
                            @if ($item->variant_name)
                                ({{ $item->variant_name }})
                            @endif
                            @if ($item->preparation_station)
                                <span class="text-muted fs-8">· {{ $item->preparation_station->label() }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <x-internal.empty-state message="No rounds yet." />
        @endforelse
    </div>
</div>
