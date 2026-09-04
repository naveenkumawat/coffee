<div class="card card-flush internal-card {{ $class ?? '' }}">
    <div class="card-header pt-6">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">Rounds</h3>
        </div>
    </div>
    <div class="card-body pt-4">
        @php
            $timingByOrderId = collect($diningTiming['rounds'] ?? [])->keyBy('order_id');
            $cancellationPolicy = app(\App\Services\Dining\DiningRoundCancellationPolicy::class);
            $actor = auth('admin')->user();
            $fmt = function (?int $seconds): string {
                if ($seconds === null) {
                    return '—';
                }
                $seconds = abs($seconds);
                $m = intdiv($seconds, 60);
                $s = $seconds % 60;

                return $m > 0 ? sprintf('%dm %02ds', $m, $s) : sprintf('%ds', $s);
            };
            $cancelReasons = \App\Enums\DiningRoundCancellationReason::options();
        @endphp
        @forelse ($session->orders as $order)
            @php
                $tickets = $order->preparations ?? collect();
                $activeTickets = $tickets->filter(fn ($ticket) => $ticket->status?->value !== 'cancelled');
                $allReady = $activeTickets->isNotEmpty()
                    && $activeTickets->every(fn ($ticket) => $ticket->status?->value === 'ready');
                $isServed = $order->served_at !== null;
                $canMarkServed = $allReady && ! $isServed
                    && ! in_array($order->status?->value, ['cancelled', 'rejected'], true)
                    && $actor?->can('markServed', $session);
                $canAccept = $order->status?->value === 'pending'
                    && ($actor?->can('transition', $order) ?? false);
                $cancellation = $cancellationPolicy->evaluate($session, $order, $actor);
                $roundTiming = $timingByOrderId->get($order->id);
                $cancelRoute = match (true) {
                    request()->routeIs('waiter.*') => route('waiter.sessions.rounds.cancel', [$session, $order]),
                    request()->routeIs('operator.*') => route('operator.dining-sessions.rounds.cancel', [$session, $order]),
                    request()->routeIs('administrator.*') => route('administrator.dining-sessions.rounds.cancel', [$session, $order]),
                    default => null,
                };
                $acceptRoute = match (true) {
                    request()->routeIs('waiter.*') => route('waiter.sessions.rounds.accept', [$session, $order]),
                    request()->routeIs('operator.*') => route('operator.dining-sessions.rounds.accept', [$session, $order]),
                    request()->routeIs('administrator.*') => route('administrator.dining-sessions.rounds.accept', [$session, $order]),
                    default => null,
                };
            @endphp
            <div class="mb-6 pb-5 border-bottom border-gray-200">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="fw-bold text-gray-900">
                        Round {{ $order->dining_round_number }} · {{ $order->order_number }}
                    </span>
                    <x-internal.order-status-badge :status="$order->status" :order="$order" />
                    @if ($isServed)
                        <span class="badge badge-light-primary">Served</span>
                    @elseif ($allReady)
                        <span class="badge badge-light-success">Ready to Serve</span>
                    @endif
                    @if ($roundTiming)
                        <span class="badge badge-light">Elapsed {{ $fmt($roundTiming['round_elapsed_seconds']) }}</span>
                        @if ($roundTiming['ready_to_serve_age_seconds'] !== null && ! $isServed)
                            <span class="badge badge-light-info">Ready age {{ $fmt($roundTiming['ready_to_serve_age_seconds']) }}</span>
                        @endif
                    @endif
                    @if ($canAccept && $acceptRoute)
                        <form method="POST" action="{{ $acceptRoute }}" class="d-inline">
                            @csrf
                            <x-internal.button label="Accept" type="submit" variant="primary" icon="ki-check" />
                        </form>
                    @endif
                    @if ($canMarkServed)
                        @php
                            $servedRoute = match (true) {
                                request()->routeIs('waiter.*') => route('waiter.sessions.rounds.served', [$session, $order]),
                                request()->routeIs('operator.*') => route('operator.dining-sessions.rounds.served', [$session, $order]),
                                request()->routeIs('administrator.*') => route('administrator.dining-sessions.rounds.served', [$session, $order]),
                                default => null,
                            };
                        @endphp
                        @if ($servedRoute)
                            <form method="POST" action="{{ $servedRoute }}" class="d-inline">
                                @csrf
                                <x-internal.button label="Mark Served" type="submit" variant="success" icon="ki-check" />
                            </form>
                        @endif
                    @endif
                </div>

                @if ($isServed && ! $cancellation['can_cancel'] && $order->status?->value !== 'cancelled')
                    <p class="text-muted fs-8 mb-3">
                        This round has already been served and cannot be cancelled normally.
                    </p>
                @elseif (! $cancellation['can_cancel'] && filled($cancellation['cancellation_blocked_reason']) && $order->status?->value !== 'cancelled')
                    <p class="text-muted fs-8 mb-3">{{ $cancellation['cancellation_blocked_reason'] }}</p>
                @endif

                @if ($cancellation['can_cancel'] && $cancelRoute)
                    <form method="POST" action="{{ $cancelRoute }}" class="mb-3 p-3 bg-light rounded">
                        @csrf
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            @if ($cancellation['cancel_requires_reason'])
                                <div>
                                    <label class="form-label fs-8 mb-1" for="cancel-reason-{{ $order->id }}">Reason</label>
                                    <select
                                        id="cancel-reason-{{ $order->id }}"
                                        name="reason"
                                        class="form-select form-select-sm"
                                        required
                                    >
                                        <option value="">Select reason</option>
                                        @foreach ($cancelReasons as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="flex-grow-1">
                                <label class="form-label fs-8 mb-1" for="cancel-notes-{{ $order->id }}">
                                    {{ $cancellation['cancel_requires_reason'] ? 'Note (optional)' : 'Note (optional)' }}
                                </label>
                                <input
                                    id="cancel-notes-{{ $order->id }}"
                                    type="text"
                                    name="notes"
                                    class="form-control form-control-sm"
                                    maxlength="500"
                                    placeholder="Optional details"
                                />
                            </div>
                            <x-internal.button label="Cancel Round" type="submit" variant="danger" icon="ki-cross" />
                        </div>
                    </form>
                @endif

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
