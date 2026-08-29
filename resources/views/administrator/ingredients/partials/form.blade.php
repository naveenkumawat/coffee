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
                    <label for="ingredient_category_id" class="required form-label">Category</label>
                    <select id="ingredient_category_id" name="ingredient_category_id" required class="form-select @error('ingredient_category_id') is-invalid @enderror" data-control="select2" data-placeholder="Select a category">
                        <option></option>
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}" @selected(old('ingredient_category_id', request('ingredient_category_id', $ingredient->ingredient_category_id)) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('ingredient_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="measurement_unit" class="required form-label">Measurement Unit</label>
                    <select id="measurement_unit" name="measurement_unit" required class="form-select @error('measurement_unit') is-invalid @enderror">
                        @foreach ($unitOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('measurement_unit', $ingredient->measurement_unit?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('measurement_unit')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">`kg` normalizes to `g` and `L` normalizes to `ml` for cost and stock calculations.</div>
                </div>
                <div class="col-md-8">
                    <label for="name" class="required form-label">Ingredient Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $ingredient->name) }}" required class="form-control @error('name') is-invalid @enderror" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="ingredient_brand_id" class="form-label">Brand</label>
                    <select id="ingredient_brand_id" name="ingredient_brand_id" class="form-select @error('ingredient_brand_id') is-invalid @enderror" data-control="select2" data-placeholder="Select a brand">
                        <option></option>
                        @foreach ($brandOptions as $id => $name)
                            <option value="{{ $id }}" @selected(old('ingredient_brand_id', $ingredient->ingredient_brand_id) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('ingredient_brand_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="slug" class="form-label">Slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug', $ingredient->slug) }}" class="form-control @error('slug') is-invalid @enderror" />
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="supplier_name" class="form-label">Supplier Name</label>
                    <input id="supplier_name" name="supplier_name" type="text" value="{{ old('supplier_name', $ingredient->supplier_name) }}" class="form-control @error('supplier_name') is-invalid @enderror" />
                    @error('supplier_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $ingredient->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-6 mb-8 internal-form-grid">
                <div class="col-md-4">
                    <label for="purchase_quantity" class="required form-label">Purchase Quantity</label>
                    <input id="purchase_quantity" name="purchase_quantity" type="number" min="0" step="0.001" value="{{ old('purchase_quantity', $ingredient->purchase_quantity) }}" required class="form-control @error('purchase_quantity') is-invalid @enderror" />
                    @error('purchase_quantity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="purchase_cost" class="required form-label">Purchase Cost</label>
                    <input id="purchase_cost" name="purchase_cost" type="number" min="0" step="0.01" value="{{ old('purchase_cost', $ingredient->purchase_cost) }}" required class="form-control @error('purchase_cost') is-invalid @enderror" />
                    @error('purchase_cost')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="current_stock" class="form-label">Current Stock</label>
                    <input id="current_stock" name="current_stock" type="number" min="0" step="0.001" value="{{ old('current_stock', $ingredient->current_stock) }}" class="form-control @error('current_stock') is-invalid @enderror" />
                    @error('current_stock')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="minimum_stock" class="form-label">Minimum Stock</label>
                    <input id="minimum_stock" name="minimum_stock" type="number" min="0" step="0.001" value="{{ old('minimum_stock', $ingredient->minimum_stock) }}" class="form-control @error('minimum_stock') is-invalid @enderror" />
                    @error('minimum_stock')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="reorder_level" class="form-label">Reorder Level</label>
                    <input id="reorder_level" name="reorder_level" type="number" min="0" step="0.001" value="{{ old('reorder_level', $ingredient->reorder_level) }}" class="form-control @error('reorder_level') is-invalid @enderror" />
                    @error('reorder_level')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-6 mb-8 internal-form-grid">
                <div class="col-md-6">
                    <label for="supplier_email" class="form-label">Supplier Email</label>
                    <input id="supplier_email" name="supplier_email" type="email" value="{{ old('supplier_email', $ingredient->supplier_email) }}" class="form-control @error('supplier_email') is-invalid @enderror" />
                    @error('supplier_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="supplier_phone" class="form-label">Supplier Phone</label>
                    <input id="supplier_phone" name="supplier_phone" type="text" value="{{ old('supplier_phone', $ingredient->supplier_phone) }}" class="form-control @error('supplier_phone') is-invalid @enderror" />
                    @error('supplier_phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="supplier_notes" class="form-label">Supplier Notes</label>
                    <textarea id="supplier_notes" name="supplier_notes" rows="3" class="form-control @error('supplier_notes') is-invalid @enderror">{{ old('supplier_notes', $ingredient->supplier_notes) }}</textarea>
                    @error('supplier_notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-10">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $ingredient->is_active))>
                <label class="form-check-label" for="is_active">Ingredient is active for future recipe and inventory use</label>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => 'Cancel', 'url' => route('administrator.ingredients.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                    ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                ]" />
            </div>
        </form>
    </div>
</div>
