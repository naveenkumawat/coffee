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
                    <label for="name" class="required form-label">Flavour Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $flavour->name) }}" required class="form-control @error('name') is-invalid @enderror" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-12">
                    <label for="image_path" class="form-label">Image Path</label>
                    <input id="image_path" name="image_path" type="text" value="{{ old('image_path', $flavour->image_path) }}" class="form-control @error('image_path') is-invalid @enderror" />
                    @error('image_path')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-12">
                    <label for="product_category_ids" class="form-label">Applicable Categories</label>
                    <select id="product_category_ids" name="product_category_ids[]" class="form-select @error('product_category_ids') is-invalid @enderror" data-control="select2" multiple data-placeholder="Select product categories">
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((string) $id, collect(old('product_category_ids', $flavour->categories?->pluck('id')->map(fn ($value) => (string) $value)->all() ?? []))->map(fn ($value) => (string) $value)->all(), true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_category_ids')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $flavour->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-10">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $flavour->is_active))>
                <label class="form-check-label" for="is_active">Flavour is active for product assignment</label>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                    ['label' => 'Cancel', 'url' => route('administrator.products.flavours.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ]" />
            </div>
        </form>
    </div>
</div>
