{{-- DomPDF A4 document — body shared with browser print. --}}
@php
    /** @var \App\Services\Invoice\OrderInvoiceData $invoice */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoiceNumber }}</title>
    <style>
        @page {
            margin: 12mm;
        }
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }
    </style>
</head>
<body>
    @include('invoices.partials.a4-body', ['invoice' => $invoice])
</body>
</html>
