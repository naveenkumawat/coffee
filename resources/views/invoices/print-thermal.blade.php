@php
    /** @var \App\Services\Invoice\OrderInvoiceData $invoice */
    $widthMm = in_array((string) ($widthMm ?? '80'), ['58', '80'], true) ? (string) $widthMm : '80';
@endphp
@include('invoices.thermal', [
    'invoice' => $invoice,
    'widthMm' => $widthMm,
])
