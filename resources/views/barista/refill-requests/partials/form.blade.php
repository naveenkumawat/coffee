<div class="card card-flush internal-card internal-form-card">
    <div class="card-header">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">{{ $title }}</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <form method="POST" action="{{ $action }}" class="form">
            @csrf

            <div class="row g-6 mb-8 internal-form-grid">
                <div class="col-md-6">
                    <label for="ingredient_id" class="required form-label">Ingredient</label>
                    <select id="ingredient_id" name="ingredient_id" required class="form-select @error('ingredient_id') is-invalid @enderror" data-control="select2" data-placeholder="Select an ingredient">
                        <option></option>
                        @foreach ($ingredientOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('ingredient_id', request('ingredient_id')) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('ingredient_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="quantity" class="required form-label">Requested Quantity</label>
                    <input id="quantity" name="quantity" type="number" min="0" step="0.001" value="{{ old('quantity', '1.000') }}" required class="form-control @error('quantity') is-invalid @enderror" />
                    @error('quantity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="measurement_unit" class="required form-label">Unit</label>
                    <select id="measurement_unit" name="measurement_unit" required class="form-select @error('measurement_unit') is-invalid @enderror">
                        @foreach (\App\Enums\IngredientUnit::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('measurement_unit') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('measurement_unit')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-6 mb-10 internal-form-grid">
                <div class="col-12">
                    <label for="notes" class="form-label">Reason / Notes</label>
                    <textarea id="notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" placeholder="Why this refill is needed">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => 'Cancel', 'url' => route('barista.refill-requests.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                    ['label' => 'Submit Request', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                ]" />
            </div>
        </form>
    </div>
</div>
