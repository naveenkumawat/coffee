@php
    /** @var \App\Services\Invoice\OrderInvoiceData $invoice */
    $widthMm = in_array((string) ($widthMm ?? '80'), ['58', '80'], true) ? (string) $widthMm : '80';
    $pageWidth = $widthMm === '58' ? '58mm' : '80mm';
    $fontSize = $widthMm === '58' ? '11px' : '12px';
    $showDiscount = bccomp($invoice->discountTotal, '0', 2) > 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $invoice->orderNumber }}</title>
    <style>
        @page {
            size: {{ $pageWidth }} auto;
            margin: 2mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 4px;
            width: {{ $pageWidth }};
            max-width: {{ $pageWidth }};
            color: #000;
            background: #fff;
            font-family: "Courier New", Courier, monospace;
            font-size: {{ $fontSize }};
            line-height: 1.35;
        }
        .center { text-align: center; }
        .brand {
            font-weight: 700;
            font-size: {{ $widthMm === '58' ? '13px' : '15px' }};
            text-transform: uppercase;
            margin: 0 0 4px;
        }
        .rule {
            border: 0;
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }
        .muted { font-size: 0.92em; }
        .item { margin: 0 0 8px; }
        .item-name { font-weight: 700; }
        .totals .row { margin: 2px 0; }
        .total-line {
            font-weight: 700;
            font-size: 1.08em;
            margin-top: 4px;
        }
        .table-banner {
            font-weight: 700;
            font-size: 1.15em;
            margin-top: 4px;
        }
        .screen-hint {
            margin: 12px 0;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 13px;
        }
        @media print {
            .no-print { display: none !important; }
            body { width: {{ $pageWidth }}; }
        }
    </style>
</head>
<body>
    <div class="no-print screen-hint">
        Thermal receipt · {{ $widthMm }}mm ·
        <button type="button" onclick="window.print()">Print</button>
        ·
        <a href="?width=80">80mm</a>
        ·
        <a href="?width=58">58mm</a>
    </div>

    <div class="center">
        <div class="brand">{{ $invoice->cafeName }}</div>
        @if ($invoice->cafePhone)
            <div class="muted">{{ $invoice->cafePhone }}</div>
        @endif
        @if ($invoice->cafeEmail)
            <div class="muted">{{ $invoice->cafeEmail }}</div>
        @endif
    </div>

    <hr class="rule">

    <div>Order # {{ $invoice->orderNumber }}</div>
    <div>{{ $invoice->placedAtLabel }}</div>
    <div>{{ $invoice->fulfilmentLabel }}</div>
    @if ($invoice->tableLabel)
        <div class="table-banner">TABLE {{ $invoice->tableLabel }}</div>
    @endif
    @if ($invoice->deliveryAddress)
        <div class="muted" style="margin-top:4px; white-space:pre-wrap;">
            @if ($invoice->deliveryContactName){{ $invoice->deliveryContactName }}@endif
            @if ($invoice->deliveryPhone) · {{ $invoice->deliveryPhone }}@endif
            @if ($invoice->deliveryContactName || $invoice->deliveryPhone)<br>@endif
            {{ $invoice->deliveryAddress }}
        </div>
    @endif

    <hr class="rule">

    @foreach ($invoice->lines as $line)
        <div class="item">
            <div class="item-name">{{ $line['product_name'] }}</div>
            @if (! empty($line['variant_name']))
                <div class="muted">{{ $line['variant_name'] }}</div>
            @endif
            <div class="row">
                <span>{{ $line['quantity'] }} x Rs {{ $line['unit_price'] }}</span>
                <span>Rs {{ $line['line_total'] }}</span>
            </div>
        </div>
    @endforeach

    <hr class="rule">

    <div class="totals">
        <div class="row"><span>Subtotal</span><span>Rs {{ $invoice->subtotal }}</span></div>
        @if ($showDiscount)
            <div class="row"><span>Discount</span><span>− Rs {{ $invoice->discountTotal }}</span></div>
        @endif
        @if ($invoice->deliveryFeeAmount)
            <div class="row"><span>Delivery</span><span>Rs {{ $invoice->deliveryFeeAmount }}</span></div>
        @endif
        <div class="row total-line"><span>TOTAL</span><span>Rs {{ $invoice->totalAmount }}</span></div>
        <div class="muted" style="margin-top:6px;">
            {{ $invoice->paymentStatusLabel }} · {{ $invoice->paymentMethodLabel }}
        </div>
    </div>

    <hr class="rule">

    <div class="center muted">{{ $invoice->footerMessage }}</div>

    @if (! empty($autoPrint))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 250);
            });
        </script>
    @endif
</body>
</html>
