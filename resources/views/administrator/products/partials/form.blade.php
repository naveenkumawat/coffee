@php
    $selectedFlavours = collect(old('product_flavour_ids', $product->flavours?->pluck('id')->all() ?? []))
        ->map(fn ($value): string => (string) $value)
        ->all();

    $variantRows = collect(old('variants', collect($variantRows)->map(function ($variant): array {
        return [
            'id' => is_object($variant) ? $variant->getKey() : ($variant['id'] ?? null),
            'name' => is_object($variant) ? $variant->name : ($variant['name'] ?? null),
            'serving_size_value' => is_object($variant) ? (string) $variant->serving_size_value : ($variant['serving_size_value'] ?? null),
            'serving_size_unit' => is_object($variant) ? $variant->serving_size_unit?->value : (($variant['serving_size_unit'] ?? null) instanceof \App\Enums\ProductServingUnit ? $variant['serving_size_unit']->value : ($variant['serving_size_unit'] ?? null)),
            'price' => is_object($variant) ? (string) $variant->price : ($variant['price'] ?? null),
            'sort_order' => is_object($variant) ? (int) $variant->sort_order : ($variant['sort_order'] ?? 0),
            'is_active' => is_object($variant) ? (bool) $variant->is_active : (bool) ($variant['is_active'] ?? true),
            'is_available' => is_object($variant) ? (bool) $variant->is_available : (bool) ($variant['is_available'] ?? true),
        ];
    })->all()))
        ->pad(3, [
            'id' => null,
            'name' => null,
            'serving_size_value' => null,
            'serving_size_unit' => 'ml',
            'price' => null,
            'sort_order' => 0,
            'is_active' => true,
            'is_available' => true,
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
                <div class="col-md-6">
                    <label for="product_category_id" class="required form-label">Category</label>
                    <select id="product_category_id" name="product_category_id" required class="form-select @error('product_category_id') is-invalid @enderror" data-control="select2" data-placeholder="Select a category">
                        <option></option>
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('product_category_id', $product->product_category_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="sku" class="form-label">SKU</label>
                    <input id="sku" name="sku" type="text" value="{{ old('sku', $product->sku) }}" class="form-control @error('sku') is-invalid @enderror" />
                    @error('sku')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-8">
                    <label for="name" class="required form-label">Product Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" required class="form-control @error('name') is-invalid @enderror" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Sort Order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $product->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-12">
                    <label for="short_description" class="form-label">Short Description</label>
                    <input id="short_description" name="short_description" type="text" value="{{ old('short_description', $product->short_description) }}" class="form-control @error('short_description') is-invalid @enderror" />
                    @error('short_description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="customer_ingredient_summary" class="form-label">Customer Ingredient Summary</label>
                    <input id="customer_ingredient_summary" name="customer_ingredient_summary" type="text" value="{{ old('customer_ingredient_summary', $product->customer_ingredient_summary) }}" class="form-control @error('customer_ingredient_summary') is-invalid @enderror" />
                    @error('customer_ingredient_summary')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="preparation_time_minutes" class="form-label">Prep Time (mins)</label>
                    <input id="preparation_time_minutes" name="preparation_time_minutes" type="number" min="0" step="1" value="{{ old('preparation_time_minutes', $product->preparation_time_minutes) }}" class="form-control @error('preparation_time_minutes') is-invalid @enderror" />
                    @error('preparation_time_minutes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="image_path" class="form-label">Image Path</label>
                    <input id="image_path" name="image_path" type="text" value="{{ old('image_path', $product->image_path) }}" class="form-control @error('image_path') is-invalid @enderror" />
                    @error('image_path')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Detailed Description</label>
                    <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="product_flavour_ids" class="form-label">Flavours</label>
                    <select id="product_flavour_ids" name="product_flavour_ids[]" class="form-select @error('product_flavour_ids') is-invalid @enderror" data-control="select2" multiple data-placeholder="Select flavours">
                        @foreach ($flavourOptions as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((string) $id, $selectedFlavours, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_flavour_ids')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="separator separator-dashed my-8"></div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
                <div>
                    <h4 class="fw-bold text-gray-900 mb-1">Sellable Variants</h4>
                    <div class="text-muted fs-7">Each row is a sellable size/variant with its own serving size and selling price.</div>
                </div>
            </div>

            <div class="row g-6 mb-8 internal-form-grid">
                @foreach ($variantRows as $index => $variant)
                    <div class="col-12">
                        <div class="border border-gray-200 rounded-3 p-5">
                            <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}" />
                            <div class="row g-4 align-items-end">
                                <div class="col-lg-3">
                                    <label for="variants_{{ $index }}_name" class="required form-label">Variant Name</label>
                                    <input id="variants_{{ $index }}_name" name="variants[{{ $index }}][name]" type="text" value="{{ $variant['name'] }}" class="form-control @error("variants.$index.name") is-invalid @enderror" />
                                    @error("variants.$index.name")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-2">
                                    <label for="variants_{{ $index }}_serving_size_value" class="required form-label">Serving Size</label>
                                    <input id="variants_{{ $index }}_serving_size_value" name="variants[{{ $index }}][serving_size_value]" type="number" min="0" step="0.001" value="{{ $variant['serving_size_value'] }}" class="form-control @error("variants.$index.serving_size_value") is-invalid @enderror" />
                                    @error("variants.$index.serving_size_value")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-2">
                                    <label for="variants_{{ $index }}_serving_size_unit" class="required form-label">Unit</label>
                                    <select id="variants_{{ $index }}_serving_size_unit" name="variants[{{ $index }}][serving_size_unit]" class="form-select @error("variants.$index.serving_size_unit") is-invalid @enderror">
                                        @foreach ($variantUnitOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($variant['serving_size_unit'] === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error("variants.$index.serving_size_unit")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-2">
                                    <label for="variants_{{ $index }}_price" class="required form-label">Selling Price</label>
                                    <input id="variants_{{ $index }}_price" name="variants[{{ $index }}][price]" type="number" min="0" step="0.01" value="{{ $variant['price'] }}" class="form-control @error("variants.$index.price") is-invalid @enderror" />
                                    @error("variants.$index.price")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-1">
                                    <label for="variants_{{ $index }}_sort_order" class="form-label">Order</label>
                                    <input id="variants_{{ $index }}_sort_order" name="variants[{{ $index }}][sort_order]" type="number" min="0" step="1" value="{{ $variant['sort_order'] }}" class="form-control @error("variants.$index.sort_order") is-invalid @enderror" />
                                    @error("variants.$index.sort_order")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-check form-switch form-check-custom form-check-solid mb-3">
                                        <input type="hidden" name="variants[{{ $index }}][is_active]" value="0">
                                        <input class="form-check-input" type="checkbox" id="variants_{{ $index }}_is_active" name="variants[{{ $index }}][is_active]" value="1" @checked((bool) $variant['is_active'])>
                                        <label class="form-check-label" for="variants_{{ $index }}_is_active">Active</label>
                                    </div>
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="hidden" name="variants[{{ $index }}][is_available]" value="0">
                                        <input class="form-check-input" type="checkbox" id="variants_{{ $index }}_is_available" name="variants[{{ $index }}][is_available]" value="1" @checked((bool) $variant['is_available'])>
                                        <label class="form-check-label" for="variants_{{ $index }}_is_available">Available</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-6 mb-8 internal-form-grid">
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                        <label class="form-check-label" for="is_active">Product is active</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="is_available" value="0">
                        <input class="form-check-input" type="checkbox" id="is_available" name="is_available" value="1" @checked(old('is_available', $product->is_available))>
                        <label class="form-check-label" for="is_available">Available to sell</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="is_featured" value="0">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
                        <label class="form-check-label" for="is_featured">Featured on the storefront</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                    ['label' => 'Cancel', 'url' => route('administrator.products.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ]" />
            </div>
        </form>
    </div>
</div>
