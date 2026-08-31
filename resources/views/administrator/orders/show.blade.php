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
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-xl-4">
            <div class="card card-flush internal-card h-xl-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Order Overview</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="d-flex flex-column gap-5">
                        <div>
                            <div class="text-muted fs-7 mb-1">Status</div>
                            <x-internal.order-status-badge :status="$order->status" :order="$order" />
                        </div>
                        @include('internal.orders.partials.fulfilment', ['order' => $order])
                        <div>
                            <div class="text-muted fs-7 mb-1">Customer</div>
                            <div class="fw-bold text-gray-900">{{ $order->customer?->name ?: 'Walk-in / internal order' }}</div>
                            <div class="text-gray-700">{{ $order->customer?->email ?: 'No linked customer account' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Assigned Barista</div>
                            <div class="text-gray-700">{{ $order->assignedBarista?->name ?: 'Not assigned yet' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Placed At</div>
                            <div class="text-gray-700">{{ $order->placed_at?->format('d M Y, h:i A') }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Customer Notes</div>
                            <div class="text-gray-700">{{ $order->customer_notes ?: 'No customer notes provided.' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="row g-5">
                <div class="col-md-4">
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5 h-100">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7">Subtotal</span>
                            <span class="fw-bold text-gray-900 mt-2">Rs {{ number_format((float) $order->subtotal, 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-5 h-100">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7">Discount</span>
                            <span class="fw-bold text-gray-900 mt-2">Rs {{ number_format((float) $order->discount_total, 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="notice d-flex bg-light-success rounded border-success border border-dashed p-5 h-100">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7">Total</span>
                            <span class="fw-bold text-gray-900 mt-2">Rs {{ number_format((float) $order->total_amount, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('internal.orders.partials.status-actions', [
        'order' => $order,
        'availableTransitions' => $availableTransitions,
        'routeName' => 'administrator.orders.status.update',
    ])

    @include('internal.orders.partials.payment-proof', [
        'order' => $order,
        'showAdminActions' => true,
    ])

    <div class="card card-flush internal-card mb-7">
        <div class="card-header pt-7">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Order Items</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Product</th>
                            <th>Variant</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                            <th>Recipe</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @foreach ($order->items as $item)
                            <tr>
                                <td>{{ $item->product_name }}</td>
                                <td>{{ $item->variant_name }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>Rs {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>Rs {{ number_format((float) $item->line_subtotal, 2) }}</td>
                                <td>{{ $item->recipe ? 'Linked' : 'Pending recipe setup' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-5 g-xl-10">
        <div class="col-xl-7">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Preparation Detail</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    @include('internal.orders.partials.preparation-cards', [
                        'items' => $order->items,
                        'emptyMessage' => 'Order items can still be tracked, but recipe instructions will appear here after recipes are assigned to the selected variants.',
                    ])
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Status History</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="timeline-label">
                        @foreach ($order->statusHistory as $entry)
                            <div class="timeline-item mb-5">
                                <div class="timeline-label fw-bold text-gray-800 fs-7">{{ $entry->created_at?->format('d M, h:i A') }}</div>
                                <div class="timeline-badge">
                                    <i class="fa fa-genderless text-primary fs-1"></i>
                                </div>
                                <div class="fw-mormal timeline-content text-muted ps-3">
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
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
