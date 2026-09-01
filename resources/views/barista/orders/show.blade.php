@extends('barista.layouts.default')

@section('page-title', $order->order_number)

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Orders', 'url' => route('barista.orders.index')],
        ['label' => $order->order_number],
    ]" />
@endsection

@section('toolbar-actions')
    <div class="internal-order-toolbar-actions d-flex flex-wrap align-items-stretch align-items-md-center gap-2 justify-content-start justify-content-lg-end">
        <x-internal.button-group :items="[
            ['label' => 'Back', 'url' => route('barista.orders.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ]" />
        @can('printInvoice', $order)
            <x-internal.invoice-dropdown
                :items="[
                    ['label' => 'Print A4', 'url' => route('barista.orders.invoice.print', $order), 'icon' => 'ki-printer', 'target' => '_blank'],
                    ['label' => 'Print 80mm Receipt', 'url' => route('barista.orders.invoice.receipt', ['order' => $order, 'width' => 80]), 'icon' => 'ki-printer', 'target' => '_blank'],
                    ['label' => 'Print 58mm Receipt', 'url' => route('barista.orders.invoice.receipt', ['order' => $order, 'width' => 58]), 'icon' => 'ki-printer', 'target' => '_blank'],
                    ['label' => 'Download PDF', 'url' => route('barista.orders.invoice.pdf', $order), 'icon' => 'ki-file-down'],
                ]"
            />
        @endcan
    </div>
@endsection

@section('content')
    @include('internal.orders.partials.order-header', [
        'order' => $order,
        'showFinancialSummary' => false,
        'showPaymentBadge' => true,
        'openUnpaidOrders' => $openUnpaidOrders ?? 0,
    ])

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
                    @include('internal.orders.partials.order-items-table', [
                        'order' => $order,
                        'showPrices' => false,
                        'showCustomerSummary' => true,
                    ])
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
                        'emptyMessage' => 'Recipe instructions will appear here once the ordered variants have recipes configured.',
                    ])
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            @include('internal.orders.partials.customer-card', ['order' => $order])

            @include('internal.orders.partials.payment-proof', [
                'order' => $order,
                'showAdminActions' => false,
                'showFinancialSummary' => false,
                'markCashRoute' => route('barista.orders.cash.receive', $order),
            ])

            @include('internal.orders.partials.status-actions', [
                'order' => $order,
                'availableTransitions' => $availableTransitions,
                'routeName' => 'barista.orders.status.update',
            ])

            @include('internal.orders.partials.status-history', ['order' => $order])
        </div>
    </div>
@endsection
