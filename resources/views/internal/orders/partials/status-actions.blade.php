@props([
    'order',
    'availableTransitions' => [],
    'routeName',
])

@if ($availableTransitions !== [])
    <div class="card card-flush internal-card mb-7">
        <div class="card-header pt-7">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Allowed Status Actions</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="d-flex flex-column gap-4">
                <div class="text-muted fs-7">
                    Current status: <span class="fw-bold text-gray-900">{{ $order->status->label() }}</span>
                </div>
                <div class="d-flex flex-wrap gap-3">
                    @foreach ($availableTransitions as $statusValue => $statusLabel)
                        <form method="POST" action="{{ route($routeName, $order) }}" class="d-inline-flex">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $statusValue }}" />
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
