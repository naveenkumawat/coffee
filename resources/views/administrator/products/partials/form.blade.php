@php
    $selectedFlavours = collect(old('product_flavour_ids', $product->flavours?->pluck('id')->all() ?? []))
        ->map(fn ($value): string => (string) $value)
        ->all();

    $selectedTags = collect(old('product_tag_ids', $product->tags?->pluck('id')->all() ?? []))
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
        ->values();

    if ($variantRows->isEmpty()) {
        $variantRows = collect([[
            'id' => null,
            'name' => 'Regular',
            'serving_size_value' => '250.000',
            'serving_size_unit' => 'ml',
            'price' => '0.00',
            'sort_order' => 1,
            'is_active' => true,
            'is_available' => true,
        ]]);
    }

    $addOnRows = collect(old('add_ons', $addOnRows ?? []))
        ->map(function ($row): array {
            return [
                'add_on_id' => is_array($row) ? ($row['add_on_id'] ?? '') : '',
                'price_override' => is_array($row) ? ($row['price_override'] ?? '') : '',
                'max_quantity' => is_array($row) ? ($row['max_quantity'] ?? '') : '',
                'sort_order' => is_array($row) ? ($row['sort_order'] ?? 10) : 10,
                'is_active' => is_array($row) ? (bool) ($row['is_active'] ?? true) : true,
                'lines' => collect(is_array($row) ? ($row['lines'] ?? []) : [])
                    ->map(fn ($line): array => [
                        'id' => $line['id'] ?? null,
                        'ingredient_id' => $line['ingredient_id'] ?? '',
                        'quantity' => $line['quantity'] ?? '',
                        'measurement_unit' => $line['measurement_unit'] ?? '',
                        'sort_order' => $line['sort_order'] ?? 1,
                    ])
                    ->values()
                    ->all(),
            ];
        })
        ->values();
@endphp

<div class="card card-flush internal-card internal-form-card">
    <div class="card-header">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">{{ $title }}</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <form method="POST" action="{{ $action }}" class="form" enctype="multipart/form-data" id="product-admin-form">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="mb-8">
                <h4 class="fw-bold text-gray-900 mb-1">Basic details</h4>
                <div class="text-muted fs-7 mb-6">Core product identity, category, and media.</div>
            </div>

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
                <div class="col-md-3">
                    <label for="product_type" class="required form-label">Product type</label>
                    <select id="product_type" name="product_type" required class="form-select @error('product_type') is-invalid @enderror">
                        @foreach (\App\Enums\ProductType::options() as $value => $label)
                            <option value="{{ $value }}" @selected((string) old('product_type', $product->product_type?->value ?? 'beverage') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('product_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="preparation_station" class="required form-label">Prep station</label>
                    <select id="preparation_station" name="preparation_station" required class="form-select @error('preparation_station') is-invalid @enderror">
                        @foreach (\App\Enums\PreparationStation::options() as $value => $label)
                            <option value="{{ $value }}" @selected((string) old('preparation_station', $product->preparation_station?->value ?? 'bar') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('preparation_station')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-8">
                    <label for="name" class="required form-label">Product Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" required class="form-control @error('name') is-invalid @enderror" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Slug is generated automatically from the name and preserved on rename.</div>
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
                    <label for="image" class="form-label">Product image</label>
                    @php
                        $currentImageUrl = \App\Support\PublicMedia::url(old('image_path', $product->image_path));
                    @endphp
                    @if ($currentImageUrl)
                        <div class="mb-3">
                            <img
                                src="{{ $currentImageUrl }}"
                                alt="{{ $product->name ?: 'Product image' }}"
                                class="rounded border"
                                style="max-width: 8rem; max-height: 8rem; object-fit: contain; background: #f5f5f5;"
                            />
                        </div>
                        <input type="hidden" name="image_path" value="{{ old('image_path', $product->image_path) }}" />
                        <div class="form-check mb-3">
                            <input
                                id="remove_image"
                                name="remove_image"
                                type="checkbox"
                                value="1"
                                class="form-check-input @error('remove_image') is-invalid @enderror"
                                @checked(old('remove_image'))
                            />
                            <label for="remove_image" class="form-check-label">Remove current image</label>
                            @error('remove_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                    <input
                        id="image"
                        name="image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                        class="form-control @error('image') is-invalid @enderror"
                    />
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">JPEG / PNG / WebP, max {{ \App\Support\PublicMedia::maxKilobytes() }} KB. Prefer WebP around 50–150 KB.</div>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Detailed Description</label>
                    <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
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
                <div class="col-md-6">
                    <label for="product_tag_ids" class="form-label">Marketing tags</label>
                    <select id="product_tag_ids" name="product_tag_ids[]" class="form-select @error('product_tag_ids') is-invalid @enderror" data-control="select2" multiple data-placeholder="Select tags (New, Top Seller, Featured…)">
                        @foreach ($tagOptions as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((string) $id, $selectedTags, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Customer badges come from these tags. Featured / New / Top Seller also power home rails.</div>
                    @error('product_tag_ids')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="separator separator-dashed my-8"></div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
                <div>
                    <h4 class="fw-bold text-gray-900 mb-1">Variants / sellable sizes</h4>
                    <div class="text-muted fs-7">Add 1..N sizes. Only submitted rows are validated — no empty placeholders required.</div>
                </div>
                <button type="button" class="btn btn-sm btn-light-primary" id="add-variant-row">
                    <i class="ki-outline ki-plus fs-5"></i> Add Variant
                </button>
            </div>
            @error('variants')
                <div class="text-danger fs-7 mb-4">{{ $message }}</div>
            @enderror

            <div id="variant-rows" class="row g-6 mb-8 internal-form-grid">
                @foreach ($variantRows as $index => $variant)
                    <div class="col-12 variant-row" data-variant-row>
                        <div class="border border-gray-200 rounded-3 p-5">
                            <div class="d-flex justify-content-between align-items-start mb-4">
                                <span class="fw-semibold text-gray-800">Variant</span>
                                <button type="button" class="btn btn-sm btn-light-danger remove-variant-row" @disabled($variantRows->count() <= 1)>Remove</button>
                            </div>
                            <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}" />
                            <div class="row g-4 align-items-end">
                                <div class="col-lg-3">
                                    <label class="required form-label">Variant / Size</label>
                                    <input name="variants[{{ $index }}][name]" type="text" value="{{ $variant['name'] }}" class="form-control" />
                                </div>
                                <div class="col-lg-2">
                                    <label class="required form-label">Serving Size</label>
                                    <input name="variants[{{ $index }}][serving_size_value]" type="number" min="0" step="0.001" value="{{ $variant['serving_size_value'] }}" class="form-control" />
                                </div>
                                <div class="col-lg-2">
                                    <label class="required form-label">Serving Unit</label>
                                    <select name="variants[{{ $index }}][serving_size_unit]" class="form-select">
                                        @foreach ($variantUnitOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($variant['serving_size_unit'] === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2">
                                    <label class="required form-label">Selling Price</label>
                                    <input name="variants[{{ $index }}][price]" type="number" min="0" step="0.01" value="{{ $variant['price'] }}" class="form-control" />
                                </div>
                                <div class="col-lg-1">
                                    <label class="form-label">Order</label>
                                    <input name="variants[{{ $index }}][sort_order]" type="number" min="0" step="1" value="{{ $variant['sort_order'] }}" class="form-control" />
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-check form-switch form-check-custom form-check-solid mb-3">
                                        <input type="hidden" name="variants[{{ $index }}][is_active]" value="0">
                                        <input class="form-check-input" type="checkbox" name="variants[{{ $index }}][is_active]" value="1" @checked((bool) $variant['is_active'])>
                                        <label class="form-check-label">Active</label>
                                    </div>
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="hidden" name="variants[{{ $index }}][is_available]" value="0">
                                        <input class="form-check-input" type="checkbox" name="variants[{{ $index }}][is_available]" value="1" @checked((bool) $variant['is_available'])>
                                        <label class="form-check-label">Available</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="separator separator-dashed my-8"></div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
                <div>
                    <h4 class="fw-bold text-gray-900 mb-1">Add-ons</h4>
                    <div class="text-muted fs-7">Reusable catalog add-ons with product-specific price and ingredient recipe.</div>
                </div>
                <button type="button" class="btn btn-sm btn-light-primary" id="add-addon-row">
                    <i class="ki-outline ki-plus fs-5"></i> Add Add-on
                </button>
            </div>

            <div id="addon-rows" class="row g-6 mb-8">
                @forelse ($addOnRows as $index => $addOnRow)
                    @include('administrator.products.partials.add-on-row', [
                        'index' => $index,
                        'addOnRow' => $addOnRow,
                        'addOnOptions' => $addOnOptions,
                        'ingredientOptions' => $ingredientOptions,
                        'ingredientUnitOptions' => $ingredientUnitOptions,
                    ])
                @empty
                @endforelse
            </div>

            <div class="row g-6 mb-8 internal-form-grid">
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                        <label class="form-check-label" for="is_active">Product is active (requires launch-ready configuration)</label>
                        @error('is_active')
                            <div class="text-danger fs-7 mt-2">{{ $message }}</div>
                        @enderror
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
                        <input type="hidden" name="is_vegetarian" value="0">
                        <input class="form-check-input" type="checkbox" id="is_vegetarian" name="is_vegetarian" value="1" @checked(old('is_vegetarian', $product->is_vegetarian))>
                        <label class="form-check-label" for="is_vegetarian">Vegetarian (metadata)</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="is_customizable" value="0">
                        <input class="form-check-input" type="checkbox" id="is_customizable" name="is_customizable" value="1" @checked(old('is_customizable', $product->is_customizable))>
                        <label class="form-check-label" for="is_customizable">Customizable</label>
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

<template id="variant-row-template">
    <div class="col-12 variant-row" data-variant-row>
        <div class="border border-gray-200 rounded-3 p-5">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <span class="fw-semibold text-gray-800">Variant</span>
                <button type="button" class="btn btn-sm btn-light-danger remove-variant-row">Remove</button>
            </div>
            <input type="hidden" name="variants[__INDEX__][id]" value="" />
            <div class="row g-4 align-items-end">
                <div class="col-lg-3">
                    <label class="required form-label">Variant / Size</label>
                    <input name="variants[__INDEX__][name]" type="text" class="form-control" />
                </div>
                <div class="col-lg-2">
                    <label class="required form-label">Serving Size</label>
                    <input name="variants[__INDEX__][serving_size_value]" type="number" min="0" step="0.001" class="form-control" />
                </div>
                <div class="col-lg-2">
                    <label class="required form-label">Serving Unit</label>
                    <select name="variants[__INDEX__][serving_size_unit]" class="form-select">
                        @foreach ($variantUnitOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="required form-label">Selling Price</label>
                    <input name="variants[__INDEX__][price]" type="number" min="0" step="0.01" class="form-control" />
                </div>
                <div class="col-lg-1">
                    <label class="form-label">Order</label>
                    <input name="variants[__INDEX__][sort_order]" type="number" min="0" step="1" value="0" class="form-control" />
                </div>
                <div class="col-lg-2">
                    <div class="form-check form-switch form-check-custom form-check-solid mb-3">
                        <input type="hidden" name="variants[__INDEX__][is_active]" value="0">
                        <input class="form-check-input" type="checkbox" name="variants[__INDEX__][is_active]" value="1" checked>
                        <label class="form-check-label">Active</label>
                    </div>
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="variants[__INDEX__][is_available]" value="0">
                        <input class="form-check-input" type="checkbox" name="variants[__INDEX__][is_available]" value="1" checked>
                        <label class="form-check-label">Available</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="addon-row-template">
    @include('administrator.products.partials.add-on-row', [
        'index' => '__INDEX__',
        'addOnRow' => [
            'add_on_id' => '',
            'price_override' => '',
            'max_quantity' => '',
            'sort_order' => 10,
            'is_active' => true,
            'lines' => [['ingredient_id' => '', 'quantity' => '', 'measurement_unit' => '', 'sort_order' => 1]],
        ],
        'addOnOptions' => $addOnOptions,
        'ingredientOptions' => $ingredientOptions,
        'ingredientUnitOptions' => $ingredientUnitOptions,
    ])
</template>

<template id="addon-recipe-line-template">
    <tr data-addon-recipe-line>
        <td>
            <select name="add_ons[__ADDON__][lines][__LINE__][ingredient_id]" class="form-select">
                <option value="">Select…</option>
                @foreach ($ingredientOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" step="0.001" min="0" name="add_ons[__ADDON__][lines][__LINE__][quantity]" class="form-control" /></td>
        <td>
            <select name="add_ons[__ADDON__][lines][__LINE__][measurement_unit]" class="form-select">
                <option value="">Select…</option>
                @foreach ($ingredientUnitOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="add_ons[__ADDON__][lines][__LINE__][sort_order]" value="1" class="form-control" /></td>
        <td><button type="button" class="btn btn-sm btn-light-danger remove-recipe-line">×</button></td>
    </tr>
</template>

@push('scripts')
<script>
(() => {
    const variantContainer = document.getElementById('variant-rows');
    const addonContainer = document.getElementById('addon-rows');
    const variantTemplate = document.getElementById('variant-row-template');
    const addonTemplate = document.getElementById('addon-row-template');
    const recipeLineTemplate = document.getElementById('addon-recipe-line-template');

    const nextIndex = (container, selector) => container.querySelectorAll(selector).length;

    const refreshVariantRemoveButtons = () => {
        const rows = variantContainer.querySelectorAll('[data-variant-row]');
        rows.forEach((row) => {
            const btn = row.querySelector('.remove-variant-row');
            if (btn) btn.disabled = rows.length <= 1;
        });
    };

    document.getElementById('add-variant-row')?.addEventListener('click', () => {
        const index = nextIndex(variantContainer, '[data-variant-row]');
        const html = variantTemplate.innerHTML.replaceAll('__INDEX__', String(index));
        variantContainer.insertAdjacentHTML('beforeend', html);
        refreshVariantRemoveButtons();
    });

    variantContainer?.addEventListener('click', (event) => {
        const btn = event.target.closest('.remove-variant-row');
        if (!btn) return;
        const rows = variantContainer.querySelectorAll('[data-variant-row]');
        if (rows.length <= 1) return;
        btn.closest('[data-variant-row]')?.remove();
        refreshVariantRemoveButtons();
    });

    document.getElementById('add-addon-row')?.addEventListener('click', () => {
        const index = nextIndex(addonContainer, '[data-addon-row]');
        const html = addonTemplate.innerHTML.replaceAll('__INDEX__', String(index));
        addonContainer.insertAdjacentHTML('beforeend', html);
    });

    addonContainer?.addEventListener('click', (event) => {
        const removeAddon = event.target.closest('.remove-addon-row');
        if (removeAddon) {
            removeAddon.closest('[data-addon-row]')?.remove();
            return;
        }

        const addLine = event.target.closest('.add-recipe-line');
        if (addLine) {
            const row = addLine.closest('[data-addon-row]');
            const addonIndex = row?.dataset.addonIndex ?? '0';
            const tbody = row.querySelector('[data-addon-recipe-body]');
            const lineIndex = tbody.querySelectorAll('[data-addon-recipe-line]').length;
            const html = recipeLineTemplate.innerHTML
                .replaceAll('__ADDON__', String(addonIndex))
                .replaceAll('__LINE__', String(lineIndex));
            tbody.insertAdjacentHTML('beforeend', html);
            return;
        }

        const removeLine = event.target.closest('.remove-recipe-line');
        if (removeLine) {
            const tbody = removeLine.closest('tbody');
            if (tbody && tbody.querySelectorAll('[data-addon-recipe-line]').length <= 1) {
                removeLine.closest('[data-addon-recipe-line]').querySelectorAll('input, select').forEach((el) => {
                    if (el.type === 'checkbox') el.checked = false;
                    else el.value = '';
                });
                return;
            }
            removeLine.closest('[data-addon-recipe-line]')?.remove();
        }
    });

    refreshVariantRemoveButtons();
})();
</script>
@endpush
