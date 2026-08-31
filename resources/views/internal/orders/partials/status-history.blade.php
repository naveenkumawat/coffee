@php
    /** @var \App\Models\Order $order */
@endphp

<div class="card card-flush internal-card {{ $cardClass ?? 'mb-0' }}">
    <div class="card-header pt-6 pb-0">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">Status History</h3>
        </div>
    </div>
    <div class="card-body pt-4">
        <div class="timeline-label">
            @forelse ($order->statusHistory as $entry)
                <div class="timeline-item mb-4">
                    <div class="timeline-label fw-bold text-gray-800 fs-8">{{ $entry->created_at?->format('d M, h:i A') }}</div>
                    <div class="timeline-badge">
                        <i class="fa fa-genderless text-primary fs-1"></i>
                    </div>
                    <div class="fw-normal timeline-content text-muted ps-3 fs-7">
                        <span class="fw-bold text-gray-900">{{ $entry->to_status->label() }}</span>
                        @if ($entry->from_status)
                            <span class="text-gray-500">from {{ $entry->from_status->label() }}</span>
                        @endif
                        <div>{{ $entry->changedBy?->name ?: 'System' }}</div>
                        @if ($entry->notes)
                            <div class="text-gray-700 mt-1">{{ $entry->notes }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-muted fs-7">No status history yet.</div>
            @endforelse
        </div>
    </div>
</div>
