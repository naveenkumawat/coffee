<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dining {{ $session->session_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #ddd; padding: 6px; text-align: left; }
        .totals { margin-top: 16px; width: 280px; margin-left: auto; }
    </style>
</head>
<body>
    <h1>{{ $cafeName }}</h1>
    <div>{{ $cafeAddress }}</div>
    <div>{{ $cafePhone }}</div>
    <p>
        Session {{ $session->session_number }} · Table {{ $session->tableDisplayLabel() }}<br>
        Status: {{ $session->status?->label() }} · {{ $paymentLabel ?? 'PAYMENT PENDING' }}
    </p>
    <table>
        <thead>
            <tr>
                <th>Round</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($session->orders as $order)
                @if (! in_array($order->status?->value, ['cancelled', 'rejected'], true))
                    @foreach ($order->items as $item)
                        <tr>
                            <td>#{{ $order->dining_round_number }}</td>
                            <td>
                                {{ $item->product_name }} @if($item->variant_name) ({{ $item->variant_name }}) @endif
                                @foreach ($item->relationLoaded('addOns') ? $item->addOns : $item->addOns()->get() as $addOn)
                                    <div>+ {{ $addOn->name }}@if($addOn->quantity > 1) ×{{ $addOn->quantity }}@endif</div>
                                @endforeach
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format((float) $item->line_subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Subtotal</td><td>{{ $bill['subtotal'] }}</td></tr>
        @forelse (($bill['discounts'] ?? []) as $discountLine)
            <tr>
                <td>
                    {{ $discountLine['name'] ?? 'Discount' }}
                    @if (! empty($discountLine['code']))
                        ({{ $discountLine['code'] }})
                    @endif
                </td>
                <td>−{{ $discountLine['amount'] ?? $bill['discount'] }}</td>
            </tr>
        @empty
            @if (bccomp((string) ($bill['discount'] ?? '0'), '0', 2) > 0)
                <tr><td>Discount</td><td>−{{ $bill['discount'] }}</td></tr>
            @endif
        @endforelse
        <tr><td>Tax</td><td>{{ $bill['tax'] }}</td></tr>
        <tr><td><strong>Total</strong></td><td><strong>{{ $bill['total'] }}</strong></td></tr>
    </table>
</body>
</html>
