@php
    /** @var \App\Models\Order $order */
    $showPrices = (bool) ($showPrices ?? false);
    $showCustomerSummary = (bool) ($showCustomerSummary ?? ! $showPrices);
    $colspan = 3 + ($showPrices ? 2 : 0) + ($showCustomerSummary ? 1 : 0);
@endphp

<div class="table-responsive internal-table-wrapper">
    <table class="table align-middle table-row-dashed fs-7 gy-3 internal-table mb-0">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                <th>Product</th>
                <th>Variant</th>
                <th class="text-end">Qty</th>
                @if ($showPrices)
                    <th class="text-end">Unit</th>
                    <th class="text-end">Line total</th>
                @endif
                @if ($showCustomerSummary)
                    <th>Customer summary</th>
                @endif
            </tr>
        </thead>
        <tbody class="fw-semibold text-gray-700">
            @forelse ($order->items as $item)
                <tr>
                    <td class="text-gray-900">{{ $item->product_name }}</td>
                    <td>{{ $item->variant_name ?: '—' }}</td>
                    <td class="text-end">{{ $item->quantity }}</td>
                    @if ($showPrices)
                        <td class="text-end">Rs {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="text-end fw-bold text-gray-900">Rs {{ number_format((float) $item->line_subtotal, 2) }}</td>
                    @endif
                    @if ($showCustomerSummary)
                        <td>{{ $item->customer_ingredient_summary ?: '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="text-muted">No items on this order.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($showPrices && $order->items->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="{{ $colspan - 1 }}" class="text-end text-muted">Subtotal</td>
                    <td class="text-end">Rs {{ number_format((float) $order->subtotal, 2) }}</td>
                </tr>
                @if ((float) $order->discount_total > 0)
                    <tr>
                        <td colspan="{{ $colspan - 1 }}" class="text-end text-muted">Discount</td>
                        <td class="text-end">Rs {{ number_format((float) $order->discount_total, 2) }}</td>
                    </tr>
                @endif
                @if ($order->tax_enabled_snapshot)
                    <tr>
                        <td colspan="{{ $colspan - 1 }}" class="text-end text-muted">
                            {{ $order->tax_label_snapshot ?: 'GST' }}
                            ({{ number_format((float) $order->tax_percent_snapshot, 2) }}%)
                            @if ($order->tax_inclusive_snapshot)
                                included
                            @endif
                        </td>
                        <td class="text-end">Rs {{ number_format((float) $order->tax_amount, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td colspan="{{ $colspan - 1 }}" class="text-end fw-bold text-gray-900">Total</td>
                    <td class="text-end fw-bold text-gray-900">Rs {{ number_format((float) $order->total_amount, 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
