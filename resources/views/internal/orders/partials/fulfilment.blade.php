<div>
    <div class="text-muted fs-7 mb-1">Fulfilment</div>
    @if ($order->isDineIn())
        <div class="fw-bold text-uppercase text-primary">Dine-in</div>
        <div class="fw-bold fs-3 text-gray-900 mt-1">
            TABLE {{ $order->tableDisplayLabel() ?: '—' }}
        </div>
    @else
        <div class="fw-bold text-gray-900">{{ $order->fulfilment_method?->label() ?? 'Takeaway' }}</div>
        @if ($order->isTakeaway())
            <div class="text-gray-700 mt-1">Pickup: {{ $order->pickup_name ?: '—' }} · {{ $order->pickup_phone ?: '—' }}</div>
            @if ($order->pickup_notes)
                <div class="text-gray-600 mt-1">{{ $order->pickup_notes }}</div>
            @endif
        @elseif ($order->isDelivery())
            <div class="text-gray-700 mt-1">{{ $order->delivery_contact_name ?: $order->customer_name }} · {{ $order->delivery_phone }}</div>
            <div class="text-gray-700 mt-1" style="white-space: pre-wrap;">{{ $order->delivery_address }}</div>
            @if ($order->delivery_notes)
                <div class="text-gray-600 mt-1">{{ $order->delivery_notes }}</div>
            @endif
            <div class="text-warning fs-8 mt-2">{{ app(\App\Services\WebsiteSetting\WebsiteSettingServiceInterface::class)->deliveryDisclaimer() }}</div>
            <div class="text-muted fs-8 mt-1">Cafe total does not include third-party delivery charges.</div>
        @endif
    @endif
</div>
