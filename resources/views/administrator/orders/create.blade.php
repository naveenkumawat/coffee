@extends('administrator.layouts.default')

@section('page-title', 'Create Order')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Orders', 'url' => route('administrator.orders.index')],
        ['label' => 'Create Order'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.orders.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
@endsection

@section('content')
    <form method="POST" action="{{ route('administrator.orders.store') }}" class="d-flex flex-column gap-7">
        @csrf

        <div class="card card-flush internal-card">
            <div class="card-header pt-7">
                <div class="card-title">
                    <h3 class="fw-bold text-gray-900">Order Details</h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="row g-6">
                    <div class="col-xl-6">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select id="customer_id" name="customer_id" class="form-select @error('customer_id') is-invalid @enderror">
                            <option value="">Walk-in / internal order</option>
                            @foreach ($customerOptions as $id => $name)
                                <option value="{{ $id }}" @selected((string) old('customer_id') === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('customer_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-6">
                        <label for="customer_notes" class="form-label">Customer Notes</label>
                        <textarea id="customer_notes" name="customer_notes" rows="3" class="form-control @error('customer_notes') is-invalid @enderror" placeholder="Optional delivery or preparation notes">{{ old('customer_notes') }}</textarea>
                        @error('customer_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-flush internal-card">
            <div class="card-header pt-7">
                <div class="card-title">
                    <h3 class="fw-bold text-gray-900">Order Items</h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex flex-column gap-5">
                    @foreach (old('items', $lineItems) as $index => $item)
                        <div class="border border-gray-200 rounded-3 p-5">
                            <div class="row g-5 align-items-end">
                                <div class="col-xl-9">
                                    <label for="items_{{ $index }}_product_variant_id" class="form-label">Product Variant</label>
                                    <select id="items_{{ $index }}_product_variant_id" name="items[{{ $index }}][product_variant_id]" class="form-select @error("items.$index.product_variant_id") is-invalid @enderror">
                                        <option value="">Select a sellable variant</option>
                                        @foreach ($variantOptions as $id => $label)
                                            <option value="{{ $id }}" @selected((string) data_get($item, 'product_variant_id') === (string) $id)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.$index.product_variant_id")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-xl-3">
                                    <label for="items_{{ $index }}_quantity" class="form-label">Quantity</label>
                                    <input id="items_{{ $index }}_quantity" name="items[{{ $index }}][quantity]" type="number" min="1" step="1" value="{{ data_get($item, 'quantity') }}" class="form-control @error("items.$index.quantity") is-invalid @enderror" />
                                    @error("items.$index.quantity")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @error('items')
                    <div class="text-danger fs-7 mt-3">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-6">
            <div class="d-flex flex-stack flex-grow-1">
                <div class="fw-semibold">
                    <h4 class="text-gray-900 fw-bold mb-1">Phase 1 workflow note</h4>
                    <span class="fs-6 text-gray-700">New orders start in Pending Payment. Payment confirmation and barista progress updates are handled from the order detail screen.</span>
                </div>
            </div>
        </div>

        <x-internal.button-group :items="[
            ['label' => 'Save', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
            ['label' => 'Cancel', 'url' => route('administrator.orders.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ]" justify="start" />
    </form>
@endsection
