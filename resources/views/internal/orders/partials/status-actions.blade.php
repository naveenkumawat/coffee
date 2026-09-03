@props([
    'order',
    'availableTransitions' => [],
    'routeName',
])

@if ($availableTransitions !== [])
    <div class="card card-flush internal-card mb-5">
        <div class="card-header pt-6 pb-0">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Allowed Status Actions</h3>
            </div>
        </div>
        <div class="card-body pt-4">
            <div class="d-flex flex-column gap-3">
                <div class="text-muted fs-8">
                    Current: <span class="fw-bold text-gray-900">{{ $order->status->label() }}</span>
                </div>
                @php
                    $hasCancel = array_key_exists('cancelled', $availableTransitions);
                    $diningNeedsReason = $order->isDiningRound()
                        && $hasCancel
                        && (
                            $order->status?->value === 'ready_for_pickup'
                            || ($order->preparations ?? collect())->contains(
                                fn ($ticket) => filled($ticket->preparing_at) || filled($ticket->ready_at)
                            )
                        );
                @endphp
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($availableTransitions as $statusValue => $statusLabel)
                        <form method="POST" action="{{ route($routeName, $order) }}" class="d-inline-flex flex-wrap align-items-end gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $statusValue }}" />
                            @if ($statusValue === 'cancelled' && $order->isDiningRound())
                                <input
                                    type="text"
                                    name="notes"
                                    class="form-control form-control-sm"
                                    style="min-width: 12rem"
                                    maxlength="500"
                                    placeholder="{{ $diningNeedsReason ? 'Cancellation reason (required)' : 'Note (optional)' }}"
                                    @if ($diningNeedsReason) required @endif
                                />
                            @endif
                            <x-internal.button
                                type="submit"
                                :label="$statusLabel"
                                :variant="in_array($statusValue, ['cancelled', 'rejected'], true) ? 'danger' : 'success'"
                                icon="ki-arrow-right"
                            />
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
