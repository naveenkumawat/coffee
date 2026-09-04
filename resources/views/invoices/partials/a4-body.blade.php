@php
    /** @var \App\Services\Invoice\OrderInvoiceData $invoice */
    $showDiscount = bccomp($invoice->discountTotal, '0', 2) > 0;
    $showLoyaltyDiscount = bccomp($invoice->loyaltyDiscountAmount, '0', 2) > 0;
    $showFreeDrink = bccomp($invoice->freeDrinkBenefit, '0', 2) > 0;
    $isCashPayment = strcasecmp($invoice->paymentMethodLabel, 'Cash') === 0;
    $paymentPaid = strcasecmp($invoice->paymentStatusLabel, 'Confirmed') === 0;
    $paymentCompact = $paymentPaid
        ? 'PAID'
        : ($isCashPayment ? 'PENDING' : strtoupper($invoice->paymentStatusLabel));
    $fulfilmentCompact = $invoice->fulfilmentLabel;
    if ($invoice->tableLabel) {
        $fulfilmentCompact .= ' · TABLE '.$invoice->tableLabel;
    } elseif ($invoice->fulfilmentCode === 'takeaway') {
        $fulfilmentCompact .= ' · PICKUP';
    }
@endphp

{{-- Shared A4 markup for browser print + DomPDF. Prefer tables over flex/grid. --}}
<style>
    .invoice-a4 {
        color: #111;
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        font-size: 11px;
        line-height: 1.45;
        background: #fff;
    }
    .invoice-a4 table { border-collapse: collapse; }
    .invoice-a4 .hdr {
        width: 100%;
        margin: 0 0 18px;
    }
    .invoice-a4 .hdr td {
        vertical-align: top;
        padding: 0;
    }
    .invoice-a4 .brand-name {
        margin: 0 0 3px;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    .invoice-a4 .brand-slogan {
        margin: 0 0 6px;
        color: #444;
        font-size: 10px;
    }
    .invoice-a4 .brand-contact {
        margin: 0;
        color: #444;
        font-size: 10px;
        line-height: 1.5;
    }
    .invoice-a4 .doc-title {
        margin: 0 0 4px;
        text-align: right;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .invoice-a4 .doc-number {
        margin: 0 0 6px;
        text-align: right;
        font-size: 13px;
        font-weight: 700;
    }
    .invoice-a4 .doc-meta {
        text-align: right;
        color: #444;
        font-size: 10px;
    }
    .invoice-a4 .section-rule {
        border: 0;
        border-top: 1px solid #222;
        margin: 0 0 10px;
    }
    .invoice-a4 .section-label {
        margin: 0 0 8px;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #555;
    }
    .invoice-a4 .summary {
        width: 100%;
        margin: 0 0 16px;
    }
    .invoice-a4 .summary td {
        width: 50%;
        vertical-align: top;
        padding: 0 12px 0 0;
    }
    .invoice-a4 .summary td + td {
        padding: 0 0 0 12px;
    }
    .invoice-a4 .kv {
        width: 100%;
        margin: 0 0 8px;
    }
    .invoice-a4 .kv td {
        padding: 0 0 3px;
        vertical-align: top;
    }
    .invoice-a4 .kv .k {
        width: 34%;
        color: #666;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding-right: 8px;
    }
    .invoice-a4 .kv .v {
        width: 66%;
        font-weight: 600;
        font-size: 11px;
    }
    .invoice-a4 .kv .v-muted {
        font-weight: 400;
        color: #333;
    }
    .invoice-a4 .items {
        width: 100%;
        margin: 0 0 14px;
    }
    .invoice-a4 .items th,
    .invoice-a4 .items td {
        padding: 7px 4px;
        vertical-align: top;
        text-align: left;
        border-bottom: 1px solid #e2e2e2;
    }
    .invoice-a4 .items th {
        padding-top: 0;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #555;
        border-bottom: 1px solid #222;
    }
    .invoice-a4 .items .col-item { width: 38%; }
    .invoice-a4 .items .col-variant { width: 22%; }
    .invoice-a4 .items .col-qty { width: 10%; text-align: right; }
    .invoice-a4 .items .col-rate,
    .invoice-a4 .items .col-amount { width: 15%; text-align: right; white-space: nowrap; }
    .invoice-a4 .items .item-name { font-weight: 600; word-wrap: break-word; }
    .invoice-a4 .items .variant { color: #555; font-size: 10px; }
    .invoice-a4 .totals-wrap {
        width: 100%;
        margin: 0 0 18px;
    }
    .invoice-a4 .totals-wrap td { padding: 0; vertical-align: top; }
    .invoice-a4 .totals {
        width: 240px;
        margin-left: auto;
    }
    .invoice-a4 .totals td {
        padding: 3px 0;
        font-size: 11px;
    }
    .invoice-a4 .totals .num {
        text-align: right;
        white-space: nowrap;
        font-weight: 600;
        padding-left: 16px;
    }
    .invoice-a4 .totals .grand td {
        padding-top: 8px;
        border-top: 1px solid #222;
        font-size: 13px;
        font-weight: 700;
    }
    .invoice-a4 .footer {
        margin-top: 8px;
        padding-top: 10px;
        border-top: 1px solid #ddd;
        color: #555;
        font-size: 10px;
    }
    @media print {
        .invoice-a4 .items thead { display: table-header-group; }
        .invoice-a4 .items tr { page-break-inside: avoid; }
    }
</style>

<div class="invoice-a4">
    <table class="hdr">
        <tr>
            <td style="width:62%;">
                <div class="brand-name">{{ $invoice->cafeName }}</div>
                @if ($invoice->cafeSlogan)
                    <div class="brand-slogan">{{ $invoice->cafeSlogan }}</div>
                @endif
                <div class="brand-contact">
                    @if ($invoice->legalBusinessName)
                        {{ $invoice->legalBusinessName }}<br>
                    @endif
                    @if ($invoice->gstin)
                        GSTIN: {{ $invoice->gstin }}<br>
                    @endif
                    @foreach (array_filter([$invoice->cafePhone, $invoice->cafeEmail, $invoice->cafeAddress]) as $line)
                        {{ $line }}@if (! $loop->last)<br>@endif
                    @endforeach
                </div>
            </td>
            <td style="width:38%;">
                <div class="doc-title">Invoice</div>
                <div class="doc-number">#{{ $invoice->invoiceNumber }}</div>
                <div class="doc-meta">
                    {{ $invoice->placedAtLabel }}
                    @if ($invoice->paidAtLabel)
                        <br>Paid {{ $invoice->paidAtLabel }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="section-label">Order summary</div>
    <hr class="section-rule">

    <table class="summary">
        <tr>
            <td>
                <table class="kv">
                    <tr>
                        <td class="k">Customer</td>
                        <td class="v">{{ $invoice->customerName }}</td>
                    </tr>
                    @if ($invoice->customerPhone)
                        <tr>
                            <td class="k">Phone</td>
                            <td class="v v-muted">{{ $invoice->customerPhone }}</td>
                        </tr>
                    @endif
                    @if ($invoice->customerEmail)
                        <tr>
                            <td class="k">Email</td>
                            <td class="v v-muted">{{ $invoice->customerEmail }}</td>
                        </tr>
                    @endif
                </table>
            </td>
            <td>
                <table class="kv">
                    <tr>
                        <td class="k">Fulfilment</td>
                        <td class="v">{{ $fulfilmentCompact }}</td>
                    </tr>
                    @if ($invoice->deliveryAddress)
                        <tr>
                            <td class="k">Deliver to</td>
                            <td class="v v-muted" style="white-space:pre-wrap;">
                                @if ($invoice->deliveryContactName){{ $invoice->deliveryContactName }}@endif
                                @if ($invoice->deliveryPhone) · {{ $invoice->deliveryPhone }}@endif
                                @if ($invoice->deliveryContactName || $invoice->deliveryPhone)

                                @endif
                                {{ $invoice->deliveryAddress }}
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td class="k">Payment Method</td>
                        <td class="v">{{ $invoice->paymentMethodLabel }}</td>
                    </tr>
                    <tr>
                        <td class="k">Payment Status</td>
                        <td class="v">{{ $paymentPaid ? 'Paid' : ($isCashPayment ? 'Pending' : $invoice->paymentStatusLabel) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-label">Items</div>
    <hr class="section-rule">

    <table class="items">
        <thead>
            <tr>
                <th class="col-item">Item</th>
                <th class="col-variant">Variant</th>
                <th class="col-qty">Qty</th>
                <th class="col-rate">Rate</th>
                <th class="col-amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td class="col-item">
                        <div class="item-name">{{ $line['product_name'] }}</div>
                    </td>
                    <td class="col-variant">
                        @if (! empty($line['variant_name']))
                            <span class="variant">{{ $line['variant_name'] }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="col-qty">{{ $line['quantity'] }}</td>
                    <td class="col-rate">Rs {{ $line['unit_price'] }}</td>
                    <td class="col-amount">Rs {{ $line['line_total'] }}</td>
                </tr>
                @foreach (($line['add_ons'] ?? []) as $addOn)
                    <tr>
                        <td class="col-item">
                            <div class="variant">
                                + {{ $addOn['name'] }}
                                @if ((int) ($addOn['quantity'] ?? 1) > 1)
                                    ×{{ $addOn['quantity'] }} each
                                @endif
                            </div>
                        </td>
                        <td class="col-variant">—</td>
                        <td class="col-qty">{{ $line['quantity'] }}</td>
                        <td class="col-rate">Rs {{ $addOn['unit_price'] }}</td>
                        <td class="col-amount">Rs {{ $addOn['total_price'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td></td>
            <td style="width:240px;">
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
                    @if ($showLoyaltyDiscount)
                        <tr>
                            <td>Loyalty reward</td>
                            <td class="num">− Rs {{ $invoice->loyaltyDiscountAmount }}</td>
                        </tr>
                    @endif
                    @if ($showFreeDrink)
                        <tr>
                            <td>Free drink (GST still applies)</td>
                            <td class="num">− Rs {{ $invoice->freeDrinkBenefit }}</td>
                        </tr>
                    @endif
                    @if ($invoice->taxEnabled)
                        <tr>
                            <td>
                                @if ($invoice->taxInclusive)
                                    {{ $invoice->taxLabel }} included @ {{ rtrim(rtrim($invoice->taxPercent, '0'), '.') }}%
                                @else
                                    {{ $invoice->taxLabel }} @ {{ rtrim(rtrim($invoice->taxPercent, '0'), '.') }}%
                                @endif
                            </td>
                            <td class="num">Rs {{ $invoice->taxAmount }}</td>
                        </tr>
                    @endif
                    @if ($invoice->deliveryFeeAmount)
                        <tr>
                            <td>Delivery fee</td>
                            <td class="num">Rs {{ $invoice->deliveryFeeAmount }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td>TOTAL</td>
                        <td class="num">Rs {{ $invoice->totalAmount }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <footer class="footer">
        {{ $invoice->footerMessage }}
    </footer>
</div>
