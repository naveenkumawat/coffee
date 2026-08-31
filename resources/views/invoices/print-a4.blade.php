@php
    /** @var \App\Services\Invoice\OrderInvoiceData $invoice */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Invoice {{ $invoice->invoiceNumber }}</title>
    <style>
        body {
            margin: 0;
            background: #f3f3f3;
            font-family: Helvetica, Arial, sans-serif;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #111;
            color: #fff;
        }
        .toolbar a, .toolbar button {
            color: #fff;
            background: transparent;
            border: 1px solid #fff;
            padding: 6px 12px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }
        .sheet {
            max-width: 210mm;
            margin: 16px auto;
            padding: 16mm 14mm;
            background: #fff;
            box-shadow: 0 1px 8px rgba(0,0,0,.12);
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet {
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            @page {
                size: A4;
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <div>A4 invoice · {{ $invoice->invoiceNumber }}</div>
        <div>
            <button type="button" onclick="window.print()">Print</button>
            <a href="javascript:window.close()">Close</a>
        </div>
    </div>
    <div class="sheet">
        @include('invoices.partials.a4-body', ['invoice' => $invoice])
    </div>
    @if (! empty($autoPrint))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 250);
            });
        </script>
    @endif
</body>
</html>
