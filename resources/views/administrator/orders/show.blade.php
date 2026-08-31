@extends('administrator.layouts.default')

@section('page-title', $order->order_number)

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Orders', 'url' => route('administrator.orders.index')],
        ['label' => $order->order_number],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.orders.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
    @can('printInvoice', $order)
        <x-internal.action-dropdown
            label="Invoice"
            :items="[
                ['label' => 'Print A4 Invoice', 'url' => route('administrator.orders.invoice.print', $order), 'icon' => 'ki-printer', 'target' => '_blank'],
                ['label' => 'Print 80mm Receipt', 'url' => route('administrator.orders.invoice.receipt', ['order' => $order, 'width' => 80]), 'icon' => 'ki-printer', 'target' => '_blank'],
                ['label' => 'Print 58mm Receipt', 'url' => route('administrator.orders.invoice.receipt', ['order' => $order, 'width' => 58]), 'icon' => 'ki-printer', 'target' => '_blank'],
                ['label' => 'Download PDF', 'url' => route('administrator.orders.invoice.pdf', $order), 'icon' => 'ki-file-down'],
            ]"
        />
    @endcan
@endsection

@section('content')
    @php
        $customerName = $order->customer?->name ?: ($order->customer_name ?: 'Walk-in / internal order');
        $customerEmail = $order->customer?->email ?: $order->customer_email;
        $customerPhone = $order->customer?->phone ?: $order->customer_phone;
        $fulfilment = $order->fulfilment_method;
    @endphp

    <div class="card card-flush internal-card mb-5">
        <div class="card-body py-5">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-4">
                <div class="min-w-0">
                    <div class="text-muted fs-8 text-uppercase mb-1">Order</div>
                    <h2 class="fw-bold text-gray-900 mb-3 user-select-all">{{ $order->order_number }}</h2>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <x-internal.order-status-badge :status="$order->status" :order="$order" />
                        <span class="badge {{ $order->payment_status?->badgeClass() ?? 'badge-light' }}">
                            {{ $order->payment_status?->label() ?? 'Pending' }}
                        </span>
                        @if ($fulfilment)
                            <span class="badge {{ $fulfilment->badgeClass() }} text-uppercase">
                                {{ $fulfilment->label() }}
                            </span>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-6 fs-7">
                        <div>
                            <div class="text-muted fs-8 text-uppercase">Customer</div>
                            <div class="fw-semibold text-gray-900">{{ $customerName }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-8 text-uppercase">Created</div>
                            <div class="fw-semibold text-gray-900">{{ $order->placed_at?->format('d M Y, h:i A') ?: '—' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-8 text-uppercase">Total</div>
                            <div class="fw-bold text-gray-900">Rs {{ number_format((float) $order->total_amount, 2) }}</div>
                        </div>
                    </div>
                </div>

                <div class="text-end min-w-125px">
                    @if ($order->isDineIn())
                        <div class="text-muted fs-8 text-uppercase mb-1">Table</div>
                        <div class="fw-bold fs-2 text-primary text-uppercase">
                            {{ $order->tableDisplayLabel() ?: '—' }}
                        </div>
                    @elseif ($order->isDelivery())
                        <div class="text-muted fs-8 text-uppercase mb-1">Delivery</div>
                        <div class="fw-semibold text-gray-900">
                            {{ $order->delivery_contact_name ?: $customerName }}
                        </div>
                        <div class="text-gray-700 fs-8 mt-1" style="white-space: pre-wrap; max-width: 16rem; margin-left: auto;">
                            {{ \Illuminate\Support\Str::limit((string) $order->delivery_address, 120) }}
                        </div>
                    @else
                        <div class="text-muted fs-8 text-uppercase mb-1">Takeaway</div>
                        <div class="fw-semibold text-gray-900">Pickup</div>
                        <div class="text-gray-700 fs-8 mt-1">
                            {{ $order->pickup_name ?: $customerName }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 g-xl-8">
        <div class="col-xl-8">
            <div class="card card-flush internal-card mb-5">
                <div class="card-header pt-6 pb-0">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Order detail</h3>
                    </div>
                </div>
                <div class="card-body pt-4">
                    <div class="mb-5">
                        <div class="text-muted fs-8 text-uppercase mb-2">Fulfilment</div>
                        @include('internal.orders.partials.fulfilment', ['order' => $order, 'showHeading' => false])
                    </div>

                    <div class="separator separator-dashed mb-5"></div>

                    <div class="text-muted fs-8 text-uppercase mb-2">Items</div>
                    <div class="table-responsive internal-table-wrapper">
                        <table class="table align-middle table-row-dashed fs-7 gy-3 internal-table mb-0">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                                    <th>Product</th>
                                    <th>Variant</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit</th>
                                    <th class="text-end">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-700">
                                @forelse ($order->items as $item)
                                    <tr>
                                        <td class="text-gray-900">{{ $item->product_name }}</td>
                                        <td>{{ $item->variant_name ?: '—' }}</td>
                                        <td class="text-end">{{ $item->quantity }}</td>
                                        <td class="text-end">Rs {{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="text-end fw-bold text-gray-900">Rs {{ number_format((float) $item->line_subtotal, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-muted">No items on this order.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end text-muted">Subtotal</td>
                                    <td class="text-end">Rs {{ number_format((float) $order->subtotal, 2) }}</td>
                                </tr>
                                @if ((float) $order->discount_total > 0)
                                    <tr>
                                        <td colspan="4" class="text-end text-muted">Discount</td>
                                        <td class="text-end">Rs {{ number_format((float) $order->discount_total, 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td colspan="4" class="text-end fw-bold text-gray-900">Total</td>
                                    <td class="text-end fw-bold text-gray-900">Rs {{ number_format((float) $order->total_amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card card-flush internal-card mb-5 mb-xl-0">
                <div class="card-header pt-6 pb-0">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Preparation Detail</h3>
                    </div>
                </div>
                <div class="card-body pt-4">
                    @include('internal.orders.partials.preparation-cards', [
                        'items' => $order->items,
                        'emptyMessage' => 'Order items can still be tracked, but recipe instructions will appear here after recipes are assigned to the selected variants.',
                    ])
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush internal-card mb-5">
                <div class="card-header pt-6 pb-0">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Customer</h3>
                    </div>
                </div>
                <div class="card-body pt-4">
                    <div class="d-flex flex-column gap-3 fs-7">
                        <div>
                            <div class="text-muted fs-8 text-uppercase mb-1">Name</div>
                            <div class="fw-semibold text-gray-900">{{ $customerName }}</div>
                        </div>
                        @if (filled($customerPhone))
                            <div>
                                <div class="text-muted fs-8 text-uppercase mb-1">Phone</div>
                                <div class="fw-semibold text-gray-900 user-select-all">{{ $customerPhone }}</div>
                            </div>
                        @endif
                        @if (filled($customerEmail))
                            <div>
                                <div class="text-muted fs-8 text-uppercase mb-1">Email</div>
                                <div class="fw-semibold text-gray-900 user-select-all">{{ $customerEmail }}</div>
                            </div>
                        @endif
                        <div>
                            <div class="text-muted fs-8 text-uppercase mb-1">Assigned barista</div>
                            <div class="text-gray-700">{{ $order->assignedBarista?->name ?: 'Not assigned yet' }}</div>
                        </div>
                        @if (filled($order->customer_notes))
                            <div>
                                <div class="text-muted fs-8 text-uppercase mb-1">Customer notes</div>
                                <div class="text-gray-700">{{ $order->customer_notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @include('internal.orders.partials.payment-proof', [
                'order' => $order,
                'showAdminActions' => true,
            ])

            @include('internal.orders.partials.status-actions', [
                'order' => $order,
                'availableTransitions' => $availableTransitions,
                'routeName' => 'administrator.orders.status.update',
            ])

            <div class="card card-flush internal-card mb-0">
                <div class="card-header pt-6 pb-0">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Status History</h3>
                    </div>
                </div>
                <div class="card-body pt-4">
                    <div class="timeline-label">
                        @forelse ($order->statusHistory as $entry)
                            <div class="timeline-item mb-4">
                                <div class="timeline-label fw-bold text-gray-800 fs-8">{{ $entry->created_at?->format('d M, h:i A') }}</div>
                                <div class="timeline-badge">
                                    <i class="fa fa-genderless text-primary fs-1"></i>
                                </div>
                                <div class="fw-normal timeline-content text-muted ps-3 fs-7">
                                    <span class="fw-bold text-gray-900">{{ $entry->to_status->label() }}</span>
                                    @if ($entry->from_status)
                                        <span class="text-gray-500">from {{ $entry->from_status->label() }}</span>
                                    @endif
                                    <div>{{ $entry->changedBy?->name ?: 'System' }}</div>
                                    @if ($entry->notes)
                                        <div class="text-gray-700 mt-1">{{ $entry->notes }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-muted fs-7">No status history yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
