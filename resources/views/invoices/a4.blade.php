{{-- DomPDF A4 document — body shared with browser print. --}}
@php
    /** @var \App\Services\Invoice\OrderInvoiceData $invoice */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoiceNumber }}</title>
</head>
<body style="margin:0;padding:24px;background:#fff;">
    @include('invoices.partials.a4-body', ['invoice' => $invoice])
</body>
</html>
