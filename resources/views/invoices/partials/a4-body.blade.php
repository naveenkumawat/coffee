@php
    /** @var \App\Services\Invoice\OrderInvoiceData $invoice */
    $showDiscount = bccomp($invoice->discountTotal, '0', 2) > 0;
@endphp

<style>
    * { box-sizing: border-box; }
    .invoice-a4 {
        color: #111;
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        font-size: 12px;
        line-height: 1.45;
        background: #fff;
    }
    .invoice-a4 .brand { margin-bottom: 18px; }
    .invoice-a4 .brand-name {
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.02em;
        margin: 0 0 4px;
    }
    .invoice-a4 .brand-slogan { margin: 0; color: #333; font-size: 11px; }
    .invoice-a4 .brand-contact { margin: 8px 0 0; color: #333; font-size: 11px; }
    .invoice-a4 .meta {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 16px;
    }
    .invoice-a4 .meta td {
        vertical-align: top;
        padding: 0;
        width: 50%;
    }
    .invoice-a4 .label {
        display: block;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #555;
        margin-bottom: 2px;
    }
    .invoice-a4 .value { font-weight: 600; }
    .invoice-a4 .fulfilment {
        margin: 0 0 14px;
        padding: 8px 10px;
        border: 1px solid #222;
    }
    .invoice-a4 .fulfilment strong { font-size: 13px; letter-spacing: 0.04em; }
    .invoice-a4 .table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 14px;
    }
    .invoice-a4 .table th,
    .invoice-a4 .table td {
        border-bottom: 1px solid #ccc;
        padding: 7px 4px;
        text-align: left;
        vertical-align: top;
    }
    .invoice-a4 .table th {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #444;
        border-bottom: 1px solid #222;
    }
    .invoice-a4 .table .num { text-align: right; white-space: nowrap; }
    .invoice-a4 .variant { color: #444; font-size: 11px; }
    .invoice-a4 .totals {
        width: 260px;
        margin-left: auto;
        border-collapse: collapse;
    }
    .invoice-a4 .totals td { padding: 4px 0; }
    .invoice-a4 .totals .num { text-align: right; font-weight: 600; }
    .invoice-a4 .totals .grand td {
        border-top: 1px solid #222;
        padding-top: 8px;
        font-size: 14px;
        font-weight: 700;
    }
    .invoice-a4 .footer {
        margin-top: 28px;
        padding-top: 10px;
        border-top: 1px solid #ccc;
        font-size: 10px;
        color: #444;
    }
    @media print {
        .invoice-a4 thead { display: table-header-group; }
        .invoice-a4 tr { page-break-inside: avoid; }
    }
</style>

<div class="invoice-a4">
    <header class="brand">
        <h1 class="brand-name">{{ $invoice->cafeName }}</h1>
        @if ($invoice->cafeSlogan)
            <p class="brand-slogan">{{ $invoice->cafeSlogan }}</p>
        @endif
        <p class="brand-contact">
            @foreach (array_filter([$invoice->cafePhone, $invoice->cafeEmail, $invoice->cafeAddress]) as $line)
                {{ $line }}@if (! $loop->last) · @endif
            @endforeach
        </p>
    </header>

    <table class="meta">
        <tr>
            <td>
                <span class="label">Invoice / Order</span>
                <div class="value">{{ $invoice->invoiceNumber }}</div>
                <div style="margin-top:8px;">
                    <span class="label">Date</span>
                    <div>{{ $invoice->placedAtLabel }}</div>
                </div>
                @if ($invoice->paidAtLabel)
                    <div style="margin-top:8px;">
                        <span class="label">Paid</span>
                        <div>{{ $invoice->paidAtLabel }}</div>
                    </div>
                @endif
            </td>
            <td>
                <span class="label">Customer</span>
                <div class="value">{{ $invoice->customerName }}</div>
                @if ($invoice->customerPhone)
                    <div>{{ $invoice->customerPhone }}</div>
                @endif
                @if ($invoice->customerEmail)
                    <div>{{ $invoice->customerEmail }}</div>
                @endif
                <div style="margin-top:8px;">
                    <span class="label">Payment</span>
                    <div>{{ $invoice->paymentMethodLabel }} · {{ $invoice->paymentStatusLabel }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="fulfilment">
        <strong>{{ $invoice->fulfilmentLabel }}</strong>
        @if ($invoice->tableLabel)
            <div style="margin-top:4px; font-size:14px; font-weight:700;">TABLE {{ $invoice->tableLabel }}</div>
        @endif
        @if ($invoice->deliveryAddress)
            <div style="margin-top:6px; white-space:pre-wrap;">
                @if ($invoice->deliveryContactName){{ $invoice->deliveryContactName }}@endif
                @if ($invoice->deliveryPhone) · {{ $invoice->deliveryPhone }}@endif
                @if ($invoice->deliveryContactName || $invoice->deliveryPhone)<br>@endif
                {{ $invoice->deliveryAddress }}
            </div>
        @endif
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Unit</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>
                        {{ $line['product_name'] }}
                        @if (! empty($line['variant_name']))
                            <div class="variant">{{ $line['variant_name'] }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">Rs {{ $line['unit_price'] }}</td>
                    <td class="num">Rs {{ $line['line_total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="num">Rs {{ $invoice->subtotal }}</td>
        </tr>
        @if ($showDiscount)
            <tr>
                <td>Discount</td>
                <td class="num">− Rs {{ $invoice->discountTotal }}</td>
            </tr>
        @endif
        @if ($invoice->deliveryFeeAmount)
            <tr>
                <td>Delivery fee</td>
                <td class="num">Rs {{ $invoice->deliveryFeeAmount }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total</td>
            <td class="num">Rs {{ $invoice->totalAmount }}</td>
        </tr>
    </table>

    <footer class="footer">
        {{ $invoice->footerMessage }}
    </footer>
</div>
