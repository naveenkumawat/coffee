@extends('administrator.layouts.default')

@section('page-title', 'Record Inventory Movement')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Inventory', 'url' => route('administrator.inventory.index')],
        ['label' => 'Record Movement'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'Back',
            'url' => route('administrator.inventory.index'),
            'variant' => 'dark',
            'icon' => 'ki-left',
        ],
        [
            'label' => 'History',
            'url' => route('administrator.inventory.history', $ingredient ? ['ingredient_id' => $ingredient->id] : []),
            'variant' => 'success',
            'icon' => 'ki-time',
        ],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-form-card">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Stock Movement Entry</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <form method="POST" action="{{ route('administrator.inventory.movements.store') }}" class="form">
                @csrf

                <div class="row g-6 mb-8 internal-form-grid">
                    <div class="col-md-6">
                        <label for="ingredient_id" class="required form-label">Ingredient</label>
                        <select id="ingredient_id" name="ingredient_id" required class="form-select @error('ingredient_id') is-invalid @enderror" data-control="select2" data-placeholder="Select an ingredient">
                            <option></option>
                            @foreach ($ingredientOptions as $id => $name)
                                <option value="{{ $id }}" @selected((string) old('ingredient_id', $ingredient?->id) === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('ingredient_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="transaction_type" class="required form-label">Transaction Type</label>
                        <select id="transaction_type" name="transaction_type" required class="form-select @error('transaction_type') is-invalid @enderror">
                            @foreach ($transactionTypeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('transaction_type', request('transaction_type')) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('transaction_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                @if ($ingredient)
                    <div class="alert alert-light-info d-flex flex-column flex-md-row align-items-md-center gap-3 mb-8">
                        <div class="fw-bold text-gray-900">{{ $ingredient->name }}</div>
                        <div class="text-muted">{{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->base_measurement_unit->value }} currently on hand</div>
                        <x-internal.stock-badge :status="$ingredient->stockStatus()" />
                    </div>
                @endif

                <div class="row g-6 mb-8 internal-form-grid">
                    <div class="col-md-4">
                        <label for="quantity" class="required form-label">Quantity</label>
                        <input id="quantity" name="quantity" type="number" min="0" step="0.001" value="{{ old('quantity', '0.000') }}" required class="form-control @error('quantity') is-invalid @enderror" />
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="measurement_unit" class="required form-label">Measurement Unit</label>
                        <select id="measurement_unit" name="measurement_unit" required class="form-select @error('measurement_unit') is-invalid @enderror">
                            @foreach ($unitOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('measurement_unit', $ingredient?->measurement_unit?->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('measurement_unit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Compatible units are derived from the ingredient base unit.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="reference_id" class="form-label">Reference ID</label>
                        <input id="reference_id" name="reference_id" type="number" min="1" step="1" value="{{ old('reference_id') }}" class="form-control @error('reference_id') is-invalid @enderror" />
                        @error('reference_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                @if ($ingredient && filled($approvedRefillRequestOptions))
                    <div class="row g-6 mb-8 internal-form-grid">
                        <div class="col-md-6">
                            <label for="inventory_refill_request_id" class="form-label">Approved Refill Request</label>
                            <select id="inventory_refill_request_id" name="inventory_refill_request_id" class="form-select @error('inventory_refill_request_id') is-invalid @enderror">
                                <option value="">No linked refill request</option>
                                @foreach ($approvedRefillRequestOptions as $id => $label)
                                    <option value="{{ $id }}" @selected((string) old('inventory_refill_request_id', $selectedRefillRequestId) === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('inventory_refill_request_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Linking an approved refill request will complete it after this stock-increasing transaction is recorded.</div>
                        </div>
                    </div>
                @endif

                <div class="row g-6 mb-8 internal-form-grid">
                    <div class="col-md-6">
                        <label for="reference_type" class="form-label">Reference Type</label>
                        <input id="reference_type" name="reference_type" type="text" value="{{ old('reference_type') }}" class="form-control @error('reference_type') is-invalid @enderror" placeholder="Purchase invoice, wastage report, audit note" />
                        @error('reference_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" placeholder="Optional reason or audit note">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end internal-form-actions">
                    <x-internal.button-group :items="[
                        ['label' => 'Cancel', 'url' => route('administrator.inventory.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                        ['label' => 'Save Movement', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                    ]" />
                </div>
            </form>
        </div>
    </div>
@endsection
