@php
    /** @var \App\Services\Invoice\OrderInvoiceData $invoice */
    $widthMm = in_array((string) ($widthMm ?? '80'), ['58', '80'], true) ? (string) $widthMm : '80';
    $pageWidth = $widthMm === '58' ? '58mm' : '80mm';
    $fontSize = $widthMm === '58' ? '10px' : '12px';
    $brandSize = $widthMm === '58' ? '12px' : '14px';
    $showDiscount = bccomp($invoice->discountTotal, '0', 2) > 0;
    $showFreeDrink = bccomp($invoice->freeDrinkBenefit, '0', 2) > 0;
    $isCashPayment = strcasecmp($invoice->paymentMethodLabel, 'Cash') === 0;
    $paymentPaid = strcasecmp($invoice->paymentStatusLabel, 'Confirmed') === 0;
    $paymentCompact = $isCashPayment
        ? ($paymentPaid ? 'CASH · PAID' : 'CASH')
        : ($paymentPaid ? 'PAID' : strtoupper($invoice->paymentStatusLabel));
    $fulfilmentCompact = $invoice->fulfilmentLabel;
    if ($invoice->tableLabel) {
        $fulfilmentCompact .= ' · TABLE '.$invoice->tableLabel;
    } elseif ($invoice->fulfilmentCode === 'takeaway') {
        $fulfilmentCompact .= ' · PICKUP';
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $invoice->orderNumber }} · {{ $widthMm }}mm</title>
    <style>
        @page {
            size: {{ $pageWidth }} auto;
            margin: 2mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0 auto;
            padding: 3mm 2mm;
            width: {{ $pageWidth }};
            max-width: {{ $pageWidth }};
            color: #000;
            background: #fff;
            font-family: "Courier New", Courier, monospace;
            font-size: {{ $fontSize }};
            line-height: 1.3;
        }
        .no-print {
            margin: 0 0 10px;
            padding: 8px;
            border: 1px solid #999;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .no-print a,
        .no-print button {
            color: #000;
            background: #fff;
            border: 1px solid #333;
            padding: 6px 10px;
            margin: 2px 2px 2px 0;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        .center { text-align: center; }
        .brand {
            margin: 0 0 2px;
            font-size: {{ $brandSize }};
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .slogan {
            margin: 0 0 4px;
            font-size: 0.92em;
        }
        .rule {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .meta,
        .line,
        .totals {
            width: 100%;
            border-collapse: collapse;
        }
        .meta td,
        .line td,
        .totals td {
            padding: 1px 0;
            vertical-align: top;
        }
        .num {
            text-align: right;
            white-space: nowrap;
            padding-left: 6px;
        }
        .item-name { font-weight: 700; word-wrap: break-word; }
        .variant { padding-left: 2px; }
        .qty-line td { padding-top: 1px; }
        .total-row td {
            padding-top: 4px;
            font-weight: 700;
            font-size: 1.08em;
            border-top: 1px solid #000;
        }
        .thanks {
            margin-top: 4px;
            text-align: center;
        }
        @media print {
            .no-print { display: none !important; }
            body {
                width: {{ $pageWidth }};
                max-width: {{ $pageWidth }};
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div><strong>Thermal receipt · {{ $widthMm }}mm</strong></div>
        <div style="margin-top:6px;">
            <button type="button" onclick="window.print()">Print</button>
            <a href="{{ request()->url() }}?width=80">80mm</a>
            <a href="{{ request()->url() }}?width=58">58mm</a>
        </div>
    </div>

    <div class="center">
        <div class="brand">{{ $invoice->cafeName }}</div>
        @if ($invoice->legalBusinessName)
            <div class="slogan">{{ $invoice->legalBusinessName }}</div>
        @endif
        @if ($invoice->gstin)
            <div class="slogan">GSTIN {{ $invoice->gstin }}</div>
        @endif
        @if ($invoice->cafeSlogan)
            <div class="slogan">{{ $invoice->cafeSlogan }}</div>
        @endif
    </div>

    <hr class="rule">

    <table class="meta">
        <tr>
            <td>Order:</td>
            <td class="num">{{ $invoice->orderNumber }}</td>
        </tr>
        <tr>
            <td>Date:</td>
            <td class="num">{{ $invoice->placedAtLabel }}</td>
        </tr>
        <tr>
            <td colspan="2">{{ $fulfilmentCompact }}</td>
        </tr>
        <tr>
            <td>Payment:</td>
            <td class="num">{{ $paymentCompact }}</td>
        </tr>
    </table>

    @if ($invoice->deliveryAddress)
        <div style="margin-top:4px; white-space:pre-wrap;">
            @if ($invoice->deliveryContactName){{ $invoice->deliveryContactName }}@endif
            @if ($invoice->deliveryPhone) · {{ $invoice->deliveryPhone }}@endif
            @if ($invoice->deliveryContactName || $invoice->deliveryPhone)

            @endif
            {{ $invoice->deliveryAddress }}
        </div>
    @endif

    <hr class="rule">

    @foreach ($invoice->lines as $line)
        <table class="line">
            <tr>
                <td class="item-name" colspan="2">{{ $line['quantity'] }} x {{ $line['product_name'] }}</td>
            </tr>
            @if (! empty($line['variant_name']))
                <tr>
                    <td class="variant">{{ $line['variant_name'] }}</td>
                    <td class="num">Rs {{ $line['line_total'] }}</td>
                </tr>
            @else
                <tr class="qty-line">
                    <td></td>
                    <td class="num">Rs {{ $line['line_total'] }}</td>
                </tr>
            @endif
            @foreach (($line['add_ons'] ?? []) as $addOn)
                <tr>
                    <td class="variant">
                        + {{ $addOn['name'] }}
                        @if ((int) ($addOn['quantity'] ?? 1) > 1)
                            ×{{ $addOn['quantity'] }}
                        @endif
                    </td>
                    <td class="num">Rs {{ $addOn['total_price'] }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach

    <hr class="rule">

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
        @if ($showFreeDrink)
            <tr>
                <td>Free drink</td>
                <td class="num">− Rs {{ $invoice->freeDrinkBenefit }}</td>
            </tr>
        @endif
        @if ($invoice->taxEnabled)
            <tr>
                <td>
                    @if ($invoice->taxInclusive)
                        {{ $invoice->taxLabel }} {{ rtrim(rtrim($invoice->taxPercent, '0'), '.') }}% incl.
                    @else
                        {{ $invoice->taxLabel }} {{ rtrim(rtrim($invoice->taxPercent, '0'), '.') }}%
                    @endif
                </td>
                <td class="num">Rs {{ $invoice->taxAmount }}</td>
            </tr>
        @endif
        @if ($invoice->deliveryFeeAmount)
            <tr>
                <td>Delivery</td>
                <td class="num">Rs {{ $invoice->deliveryFeeAmount }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td>TOTAL</td>
            <td class="num">Rs {{ $invoice->totalAmount }}</td>
        </tr>
    </table>

    <hr class="rule">

    <div class="thanks">{{ $invoice->footerMessage }}</div>
</body>
</html>
