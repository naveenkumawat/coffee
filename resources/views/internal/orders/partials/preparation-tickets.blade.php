@php
    $preparations = collect($preparations ?? []);
@endphp

@if ($preparations->isEmpty())
    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-6">
        <div class="fw-semibold">
            <h4 class="text-gray-900 fw-bold mb-1">No preparation tickets yet</h4>
            <span class="fs-6 text-gray-700">Tickets appear after the order is accepted.</span>
        </div>
    </div>
@else
    <div class="row g-4">
        @foreach ($preparations as $ticket)
            <div class="col-md-6">
                <div class="border border-gray-200 rounded-3 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <div class="fw-bold text-gray-900">{{ $ticket->station?->label() ?: 'Station' }}</div>
                            <div class="text-muted fs-8">{{ $ticket->items()->count() }} item(s)</div>
                        </div>
                        <span class="badge {{ $ticket->status?->badgeClass() }}">{{ $ticket->status?->label() }}</span>
                    </div>
                    <ul class="list-unstyled mb-0">
                        @foreach ($ticket->items() as $item)
                            <li class="fs-7 text-gray-700 mb-1">{{ $item->quantity }}× {{ $item->product_name }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
@endif
