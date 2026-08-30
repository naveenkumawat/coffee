@php
    $rows = collect(old('lines', collect($lineRows)->map(function ($line): array {
        return [
            'id' => is_object($line) ? $line->getKey() : ($line['id'] ?? null),
            'ingredient_id' => is_object($line) ? $line->ingredient_id : ($line['ingredient_id'] ?? null),
            'quantity' => is_object($line) ? (string) $line->quantity : ($line['quantity'] ?? null),
            'measurement_unit' => is_object($line) ? $line->measurement_unit?->value : ($line['measurement_unit'] ?? 'g'),
            'sort_order' => is_object($line) ? (int) $line->sort_order : ($line['sort_order'] ?? 0),
            'show_to_customer' => is_object($line) ? (bool) $line->show_to_customer : (bool) ($line['show_to_customer'] ?? false),
            'customer_label' => is_object($line) ? $line->customer_label : ($line['customer_label'] ?? null),
        ];
    })->all()))
        ->pad(5, [
            'id' => null,
            'ingredient_id' => null,
            'quantity' => null,
            'measurement_unit' => 'g',
            'sort_order' => 0,
            'show_to_customer' => false,
            'customer_label' => null,
        ])
        ->values();
@endphp

<div class="card card-flush internal-card internal-form-card">
    <div class="card-header">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">{{ $title }}</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <form method="POST" action="{{ $action }}" class="form">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="row g-6 mb-8 internal-form-grid">
                <div class="col-md-8">
                    <label for="product_variant_id" class="required form-label">Product Variant</label>
                    <select id="product_variant_id" name="product_variant_id" required class="form-select @error('product_variant_id') is-invalid @enderror" data-control="select2" data-placeholder="Select a product variant">
                        <option></option>
                        @foreach ($variantOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('product_variant_id', $selectedVariantId) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_variant_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="preparation_notes" class="form-label">Preparation Notes / Instructions</label>
                    <textarea id="preparation_notes" name="preparation_notes" rows="4" class="form-control @error('preparation_notes') is-invalid @enderror">{{ old('preparation_notes', $recipe->preparation_notes) }}</textarea>
                    @error('preparation_notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="separator separator-dashed my-8"></div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
                <div>
                    <h4 class="fw-bold text-gray-900 mb-1">Ingredient Lines</h4>
                    <div class="text-muted fs-7">Compatible units follow each ingredient's base unit. Duplicate ingredients are blocked. Customer-visible lines appear on the product detail page without quantities.</div>
                </div>
            </div>

            <div class="row g-6 mb-8 internal-form-grid">
                @foreach ($rows as $index => $line)
                    <div class="col-12">
                        <div class="border border-gray-200 rounded-3 p-5">
                            <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line['id'] }}">
                            <div class="row g-4 align-items-end">
                                <div class="col-lg-5">
                                    <label for="lines_{{ $index }}_ingredient_id" class="required form-label">Ingredient</label>
                                    <select id="lines_{{ $index }}_ingredient_id" name="lines[{{ $index }}][ingredient_id]" class="form-select @error("lines.$index.ingredient_id") is-invalid @enderror" data-control="select2" data-placeholder="Select an ingredient">
                                        <option></option>
                                        @foreach ($ingredientOptions as $id => $name)
                                            <option value="{{ $id }}" @selected((string) $line['ingredient_id'] === (string) $id)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error("lines.$index.ingredient_id")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-2">
                                    <label for="lines_{{ $index }}_quantity" class="required form-label">Quantity</label>
                                    <input id="lines_{{ $index }}_quantity" name="lines[{{ $index }}][quantity]" type="number" min="0" step="0.001" value="{{ $line['quantity'] }}" class="form-control @error("lines.$index.quantity") is-invalid @enderror" />
                                    @error("lines.$index.quantity")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-2">
                                    <label for="lines_{{ $index }}_measurement_unit" class="required form-label">Unit</label>
                                    <select id="lines_{{ $index }}_measurement_unit" name="lines[{{ $index }}][measurement_unit]" class="form-select @error("lines.$index.measurement_unit") is-invalid @enderror">
                                        @foreach ($unitOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($line['measurement_unit'] === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error("lines.$index.measurement_unit")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-1">
                                    <label for="lines_{{ $index }}_sort_order" class="form-label">Order</label>
                                    <input id="lines_{{ $index }}_sort_order" name="lines[{{ $index }}][sort_order]" type="number" min="0" step="1" value="{{ $line['sort_order'] }}" class="form-control @error("lines.$index.sort_order") is-invalid @enderror" />
                                    @error("lines.$index.sort_order")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label d-block">Customer</label>
                                    <div class="form-check form-switch form-check-custom form-check-solid mt-2">
                                        <input type="hidden" name="lines[{{ $index }}][show_to_customer]" value="0">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="lines_{{ $index }}_show_to_customer"
                                            name="lines[{{ $index }}][show_to_customer]"
                                            value="1"
                                            @checked($line['show_to_customer'])
                                        >
                                        <label class="form-check-label" for="lines_{{ $index }}_show_to_customer">Show to customer</label>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <label for="lines_{{ $index }}_customer_label" class="form-label">Customer label</label>
                                    <input
                                        id="lines_{{ $index }}_customer_label"
                                        name="lines[{{ $index }}][customer_label]"
                                        type="text"
                                        maxlength="120"
                                        value="{{ $line['customer_label'] }}"
                                        placeholder="Optional friendlier name (e.g. Vanilla)"
                                        class="form-control @error("lines.$index.customer_label") is-invalid @enderror"
                                    />
                                    @error("lines.$index.customer_label")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if (! empty($costing))
                <div class="card bg-light-success border border-success border-dashed mb-8">
                    <div class="card-body py-5">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="text-muted fs-7 mb-1">Production Cost</div>
                                <div class="fw-bold text-gray-900">Rs {{ number_format((float) $costing['production_cost'], 4) }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted fs-7 mb-1">Selling Price</div>
                                <div class="fw-bold text-gray-900">Rs {{ number_format((float) $costing['selling_price'], 4) }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted fs-7 mb-1">Gross Profit</div>
                                <div class="fw-bold text-gray-900">Rs {{ number_format((float) $costing['gross_profit'], 4) }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted fs-7 mb-1">Margin</div>
                                <div class="fw-bold text-gray-900">{{ $costing['margin_percentage'] }}%</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="form-check form-switch form-check-custom form-check-solid mb-10">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $recipe->is_active))>
                <label class="form-check-label" for="is_active">Recipe is active for barista preparation use</label>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                    ['label' => 'Cancel', 'url' => route('administrator.recipes.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ]" />
            </div>
        </form>
    </div>
</div>
