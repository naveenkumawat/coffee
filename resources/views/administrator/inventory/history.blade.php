@extends('administrator.layouts.default')

@section('page-title', 'Inventory History')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Inventory', 'url' => route('administrator.inventory.index')],
        ['label' => 'History'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'Overview',
            'url' => route('administrator.inventory.index'),
            'variant' => 'dark',
            'icon' => 'ki-left',
        ],
        [
            'label' => 'Record Movement',
            'url' => route('administrator.inventory.movements.create', request()->only('ingredient_id')),
            'variant' => 'success',
            'icon' => 'ki-plus',
        ],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.inventory.history') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-3 col-md-6">
                    <label for="ingredient_id" class="form-label">Ingredient</label>
                    <select id="ingredient_id" name="ingredient_id" class="form-select" data-control="select2" data-placeholder="All ingredients">
                        <option></option>
                        @foreach ($ingredientOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('ingredient_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="ingredient_category_id" class="form-label">Category</label>
                    <select id="ingredient_category_id" name="ingredient_category_id" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('ingredient_category_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="ingredient_brand_id" class="form-label">Brand</label>
                    <select id="ingredient_brand_id" name="ingredient_brand_id" class="form-select">
                        <option value="">All brands</option>
                        @foreach ($brandOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('ingredient_brand_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="transaction_type" class="form-label">Type</label>
                    <select id="transaction_type" name="transaction_type" class="form-select">
                        <option value="">All types</option>
                        @foreach ($transactionTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('transaction_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-3">
                    <label for="created_by" class="form-label">Performed By</label>
                    <select id="created_by" name="created_by" class="form-select">
                        <option value="">All users</option>
                        @foreach ($userOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('created_by') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="date_from" class="form-label">From</label>
                    <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="form-control" />
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="date_to" class="form-label">To</label>
                    <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="form-control" />
                </div>
                <div class="col-xl-3 col-md-12">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.inventory.history'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
                    ]" justify="start" />
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Ingredient</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Stock Before</th>
                            <th>Stock After</th>
                            <th>Reference</th>
                            <th>Performed By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($transactions as $transaction)
                            @php
                                $isDecrease = $transaction->transaction_type->isDecrease();
                                $isIncrease = $transaction->transaction_type->isIncrease();
                                $quantityPrefix = $isDecrease ? '-' : ($isIncrease ? '+' : '=');
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $transaction->ingredient?->name }}</span>
                                        <span class="text-muted">{{ $transaction->ingredient?->brand?->name ?: 'No brand assigned' }}</span>
                                        <span class="text-gray-500 fs-7">{{ $transaction->ingredient?->category?->name }}</span>
                                    </div>
                                </td>
                                <td>{{ $transaction->transaction_type->label() }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="{{ $isDecrease ? 'text-danger' : ($isIncrease ? 'text-success' : 'text-gray-900') }}">
                                            {{ $quantityPrefix }}{{ number_format((float) $transaction->quantity, 3) }} {{ $transaction->measurement_unit->value }}
                                        </span>
                                        <span class="text-gray-500 fs-7">Base {{ number_format((float) $transaction->base_quantity, 3) }} {{ $transaction->base_measurement_unit->value }}</span>
                                    </div>
                                </td>
                                <td>{{ number_format((float) $transaction->stock_before, 3) }} {{ $transaction->base_measurement_unit->value }}</td>
                                <td>{{ number_format((float) $transaction->stock_after, 3) }} {{ $transaction->base_measurement_unit->value }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        @if (in_array($transaction->transaction_type->value, ['sale_consumption', 'sale_reversal'], true))
                                            <span class="text-gray-900">{{ $transaction->transaction_type->label() }}</span>
                                            <span class="text-gray-500 fs-7">{{ $transaction->notes ?: 'Order item #'.$transaction->reference_id }}</span>
                                        @else
                                            <span>{{ $transaction->reference_type ?: 'Manual entry' }}</span>
                                            <span class="text-gray-500 fs-7">{{ $transaction->reference_id ? '#'.$transaction->reference_id : ($transaction->notes ?: 'No notes') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $transaction->createdBy?->name ?: 'System' }}</td>
                                <td>{{ $transaction->created_at?->format('d M Y, h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-10">No inventory transactions matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $transactions->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
