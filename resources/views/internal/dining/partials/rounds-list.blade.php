<div class="card card-flush internal-card {{ $class ?? '' }}">
    <div class="card-header pt-6">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">Rounds</h3>
        </div>
    </div>
    <div class="card-body pt-4">
        @forelse ($session->orders as $order)
            <div class="mb-5">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="fw-bold text-gray-900">
                        Round {{ $order->dining_round_number }} · {{ $order->order_number }}
                    </span>
                    <x-internal.order-status-badge :status="$order->status" :order="$order" />
                </div>
                <ul class="mb-0">
                    @foreach ($order->items as $item)
                        <li>
                            {{ $item->quantity }} × {{ $item->product_name }}
                            @if ($item->variant_name)
                                ({{ $item->variant_name }})
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
