@php
    $lineRows = old('lines', $lineRows ?? []);
    if ($lineRows === []) {
        $lineRows = [['ingredient_id' => '', 'quantity' => '', 'measurement_unit' => '', 'sort_order' => 1]];
    }
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
                <div class="col-md-6">
                    <label for="name" class="required form-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $addOn->name) }}" required class="form-control @error('name') is-invalid @enderror" />
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="default_price" class="required form-label">Default price</label>
                    <input id="default_price" name="default_price" type="number" min="0" step="0.01" value="{{ old('default_price', $addOn->default_price) }}" required class="form-control @error('default_price') is-invalid @enderror" />
                    @error('default_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="sort_order" class="form-label">Sort order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $addOn->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                    @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $addOn->description) }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-8">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $addOn->is_active))>
                <label class="form-check-label" for="is_active">Add-on is active</label>
            </div>

            <div class="mb-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold mb-0">Recipe lines</h4>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted fs-7 text-uppercase">
                                <th>Ingredient</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lineRows as $index => $line)
                                <tr>
                                    <td>
                                        @if (!empty($line['id']))
                                            <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line['id'] }}" />
                                        @endif
                                        <select name="lines[{{ $index }}][ingredient_id]" class="form-select">
                                            <option value="">Select…</option>
                                            @foreach ($ingredientOptions as $id => $label)
                                                <option value="{{ $id }}" @selected((string) ($line['ingredient_id'] ?? '') === (string) $id)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.001" min="0" name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '' }}" class="form-control" />
                                    </td>
                                    <td>
                                        <select name="lines[{{ $index }}][measurement_unit]" class="form-select">
                                            <option value="">Select…</option>
                                            @foreach ($unitOptions as $value => $label)
                                                <option value="{{ $value }}" @selected((string) ($line['measurement_unit'] ?? '') === (string) $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="lines[{{ $index }}][sort_order]" value="{{ $line['sort_order'] ?? ($index + 1) }}" class="form-control" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-muted fs-8">Leave an ingredient blank to skip that row. Add more lines by saving and editing again, or submit multiple rows via validation-friendly payloads.</div>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                    ['label' => 'Cancel', 'url' => route('administrator.add-ons.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ]" />
            </div>
        </form>
    </div>
</div>
