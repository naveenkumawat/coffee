@extends('barista.layouts.default')

@section('page-title', 'Refill Request Details')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Refill Requests', 'url' => route('barista.refill-requests.index')],
        ['label' => 'Details'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('barista.refill-requests.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        [
            'label' => 'New Request',
            'url' => route('barista.refill-requests.create', ['ingredient_id' => $request->ingredient_id]),
            'variant' => 'success',
            'icon' => 'ki-plus',
            'disabled' => $request->status->isActive(),
        ],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10">
        <div class="col-xl-8">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Request Summary</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="row g-6">
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Ingredient</div>
                            <div class="fw-bold text-gray-900">{{ $request->ingredient?->name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Status</div>
                            <x-internal.refill-request-status-badge :status="$request->status" />
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Requested Quantity</div>
                            <div class="fw-bold text-gray-900">{{ number_format((float) $request->quantity, 3) }} {{ $request->measurement_unit->value }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Normalized Quantity</div>
                            <div class="fw-bold text-gray-900">{{ number_format((float) $request->base_quantity, 3) }} {{ $request->base_measurement_unit->value }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted fs-7 mb-1">Reason / Notes</div>
                            <div class="text-gray-700">{{ $request->notes ?: 'No notes provided.' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush internal-card mb-5">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Request Activity</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Requested By</div>
                        <div class="fw-bold text-gray-900">{{ $request->requestedBy?->name }}</div>
                    </div>
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Requested At</div>
                        <div class="fw-bold text-gray-900">{{ $request->created_at?->format('d M Y, h:i A') }}</div>
                    </div>
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Reviewed By</div>
                        <div class="text-gray-700">{{ $request->reviewedBy?->name ?: 'Awaiting administrator review' }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-7 mb-1">Review Notes</div>
                        <div class="text-gray-700">{{ $request->review_notes ?: 'No review notes yet.' }}</div>
                    </div>
                </div>
            </div>

            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Inventory Context</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Current Stock</div>
                        <div class="fw-bold text-gray-900">{{ number_format((float) $request->ingredient?->current_stock, 3) }} {{ $request->ingredient?->base_measurement_unit?->value }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-7 mb-1">Current Stock Status</div>
                        <x-internal.stock-badge :status="$request->ingredient->stockStatus()" />
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
