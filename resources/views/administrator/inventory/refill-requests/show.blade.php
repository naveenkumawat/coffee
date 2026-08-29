@extends('administrator.layouts.default')

@section('page-title', 'Refill Request Details')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Inventory', 'url' => route('administrator.inventory.index')],
        ['label' => 'Refill Requests', 'url' => route('administrator.inventory.refill-requests.index')],
        ['label' => 'Details'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.inventory.refill-requests.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        [
            'label' => 'Fulfill Through Stock',
            'url' => route('administrator.inventory.movements.create', ['ingredient_id' => $request->ingredient_id, 'inventory_refill_request_id' => $request->id, 'transaction_type' => 'stock_added']),
            'variant' => 'success',
            'icon' => 'ki-plus',
            'disabled' => $request->status !== \App\Enums\InventoryRefillRequestStatus::Approved,
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
                        <h3 class="fw-bold text-gray-900">Review</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Requested By</div>
                        <div class="fw-bold text-gray-900">{{ $request->requestedBy?->name }}</div>
                    </div>
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Reviewed By</div>
                        <div class="text-gray-700">{{ $request->reviewedBy?->name ?: 'Not reviewed yet' }}</div>
                    </div>
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Reviewed At</div>
                        <div class="text-gray-700">{{ $request->reviewed_at?->format('d M Y, h:i A') ?: 'Not reviewed yet' }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-7 mb-1">Review Notes</div>
                        <div class="text-gray-700">{{ $request->review_notes ?: 'No review notes yet.' }}</div>
                    </div>
                </div>
            </div>

            @if ($request->status === \App\Enums\InventoryRefillRequestStatus::Pending)
                <div class="card card-flush internal-card">
                    <div class="card-header pt-7">
                        <div class="card-title">
                            <h3 class="fw-bold text-gray-900">Administrator Decision</h3>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        <form method="POST" action="{{ route('administrator.inventory.refill-requests.approve', $request) }}" class="mb-6">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="approved">
                            <label for="approve_review_notes" class="form-label">Approval Notes</label>
                            <textarea id="approve_review_notes" name="review_notes" rows="3" class="form-control @error('review_notes') is-invalid @enderror" placeholder="Optional approval note">{{ old('review_notes') }}</textarea>
                            @error('review_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="mt-5">
                                <x-internal.button-group :items="[
                                    ['label' => 'Approve', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                                ]" justify="start" />
                            </div>
                        </form>

                        <form method="POST" action="{{ route('administrator.inventory.refill-requests.reject', $request) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="rejected">
                            <label for="reject_review_notes" class="form-label">Rejection Notes</label>
                            <textarea id="reject_review_notes" name="review_notes" rows="3" class="form-control @error('review_notes') is-invalid @enderror" placeholder="Why this request is being rejected">{{ old('review_notes') }}</textarea>
                            @error('review_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="mt-5">
                                <x-internal.button-group :items="[
                                    ['label' => 'Reject', 'type' => 'submit', 'variant' => 'danger', 'icon' => 'ki-cross'],
                                ]" justify="start" />
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
