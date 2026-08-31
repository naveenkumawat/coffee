@php
    use App\Enums\OrderFulfilmentMethod;
    use App\Support\CustomerEmailBrand;
    $brandSnapshot = CustomerEmailBrand::snapshot();
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;border:1px solid #e4d4c4;border-radius:8px;overflow:hidden;">
    <tr>
        <td style="padding:12px 14px;background:#f7efe6;font-weight:700;">
            Order {{ $order->order_number }}
        </td>
    </tr>
    <tr>
        <td style="padding:12px 14px;">
            <p style="margin:0 0 8px;"><strong>Status:</strong> {{ $order->customerStatusLabel() }}</p>
            <p style="margin:0 0 8px;"><strong>Fulfilment:</strong> {{ $order->fulfilment_method?->label() ?? 'Takeaway' }}</p>
            @if ($order->placed_at)
                <p style="margin:0 0 8px;"><strong>Placed:</strong> {{ $order->placed_at->timezone(config('app.timezone'))->format('d M Y, g:i A') }}</p>
            @endif
            <p style="margin:0 0 12px;"><strong>Total:</strong> ₹{{ number_format((float) $order->total_amount, 2) }}</p>

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                <tr>
                    <th align="left" style="padding:6px 0;border-bottom:1px solid #eadfce;font-size:12px;">Item</th>
                    <th align="right" style="padding:6px 0;border-bottom:1px solid #eadfce;font-size:12px;">Qty</th>
                    <th align="right" style="padding:6px 0;border-bottom:1px solid #eadfce;font-size:12px;">Amount</th>
                </tr>
                @foreach ($order->items as $item)
                    <tr>
                        <td style="padding:8px 0;border-bottom:1px solid #f1e7dc;font-size:13px;">
                            {{ $item->product_name }}
                            @if (filled($item->variant_name))
                                <div style="color:#6b5646;font-size:12px;">{{ $item->variant_name }}</div>
                            @endif
                        </td>
                        <td align="right" style="padding:8px 0;border-bottom:1px solid #f1e7dc;font-size:13px;">{{ $item->quantity }}</td>
                        <td align="right" style="padding:8px 0;border-bottom:1px solid #f1e7dc;font-size:13px;">₹{{ number_format((float) $item->line_subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </table>

            @if ($order->fulfilment_method === OrderFulfilmentMethod::Takeaway)
                @if (filled($brandSnapshot['address']))
                    <p style="margin:12px 0 0;"><strong>Pickup address:</strong><br>{!! nl2br(e($brandSnapshot['address'])) !!}</p>
                @endif
            @elseif ($order->fulfilment_method === OrderFulfilmentMethod::Delivery)
                @if (filled($order->delivery_address))
                    <p style="margin:12px 0 0;"><strong>Delivery address:</strong><br>{!! nl2br(e($order->delivery_address)) !!}</p>
                @endif
                @if (filled($brandSnapshot['delivery_disclaimer']))
                    <p style="margin:8px 0 0;font-size:12px;color:#6b5646;">{{ $brandSnapshot['delivery_disclaimer'] }}</p>
                @endif
            @elseif ($order->fulfilment_method === OrderFulfilmentMethod::DineIn)
                @if (filled($order->table_name_snapshot))
                    <p style="margin:12px 0 0;"><strong>Table:</strong> {{ $order->table_name_snapshot }}</p>
                @endif
            @endif
        </td>
    </tr>
</table>
