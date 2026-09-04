@php
    $lines = $addOnRow['lines'] ?? [];
    if ($lines === []) {
        $lines = [['ingredient_id' => '', 'quantity' => '', 'measurement_unit' => '', 'sort_order' => 1]];
    }
@endphp
<div class="col-12" data-addon-row data-addon-index="{{ $index }}">
    <div class="border border-gray-200 rounded-3 p-5">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <span class="fw-semibold text-gray-800">Product add-on</span>
            <button type="button" class="btn btn-sm btn-light-danger remove-addon-row">Remove</button>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <label class="required form-label">Add-on</label>
                <select name="add_ons[{{ $index }}][add_on_id]" class="form-select @error("add_ons.$index.add_on_id") is-invalid @enderror" data-control="select2" data-placeholder="Select…">
                    <option value="">Select…</option>
                    @foreach ($addOnOptions as $id => $label)
                        <option value="{{ $id }}" @selected((string) ($addOnRow['add_on_id'] ?? '') === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
                @error("add_ons.$index.add_on_id")
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-lg-2">
                <label class="form-label">Selling price</label>
                <input type="number" min="0" step="0.01" name="add_ons[{{ $index }}][price_override]" value="{{ $addOnRow['price_override'] ?? '' }}" class="form-control" placeholder="Default" />
            </div>
            <div class="col-lg-2">
                <label class="form-label">Max qty</label>
                <input type="number" min="1" step="1" name="add_ons[{{ $index }}][max_quantity]" value="{{ $addOnRow['max_quantity'] ?? '' }}" class="form-control" />
            </div>
            <div class="col-lg-2">
                <label class="form-label">Sort</label>
                <input type="number" min="0" step="1" name="add_ons[{{ $index }}][sort_order]" value="{{ $addOnRow['sort_order'] ?? 10 }}" class="form-control" />
            </div>
            <div class="col-lg-2 d-flex align-items-end">
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input type="hidden" name="add_ons[{{ $index }}][is_active]" value="0">
                    <input class="form-check-input" type="checkbox" name="add_ons[{{ $index }}][is_active]" value="1" @checked((bool) ($addOnRow['is_active'] ?? true))>
                    <label class="form-check-label">Active</label>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Recipe (this product)</h5>
            <button type="button" class="btn btn-sm btn-light add-recipe-line">+ Ingredient</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-muted fs-7 text-uppercase">
                        <th>Ingredient</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Order</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-addon-recipe-body>
                    @foreach ($lines as $lineIndex => $line)
                        <tr data-addon-recipe-line>
                            <td>
                                @if (!empty($line['id']))
                                    <input type="hidden" name="add_ons[{{ $index }}][lines][{{ $lineIndex }}][id]" value="{{ $line['id'] }}" />
                                @endif
                                <select name="add_ons[{{ $index }}][lines][{{ $lineIndex }}][ingredient_id]" class="form-select" data-control="select2" data-placeholder="Select…">
                                    <option value="">Select…</option>
                                    @foreach ($ingredientOptions as $id => $label)
                                        <option value="{{ $id }}" @selected((string) ($line['ingredient_id'] ?? '') === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.001" min="0" name="add_ons[{{ $index }}][lines][{{ $lineIndex }}][quantity]" value="{{ $line['quantity'] ?? '' }}" class="form-control" />
                            </td>
                            <td>
                                <select name="add_ons[{{ $index }}][lines][{{ $lineIndex }}][measurement_unit]" class="form-select">
                                    <option value="">Select…</option>
                                    @foreach ($ingredientUnitOptions as $value => $label)
                                        <option value="{{ $value }}" @selected((string) ($line['measurement_unit'] ?? '') === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" name="add_ons[{{ $index }}][lines][{{ $lineIndex }}][sort_order]" value="{{ $line['sort_order'] ?? ($lineIndex + 1) }}" class="form-control" />
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-light-danger remove-recipe-line">×</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
