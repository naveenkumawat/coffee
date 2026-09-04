@php
    // Catalog-only add-on form (no global recipe editor).
@endphp
<div class="card card-flush internal-card internal-form-card">
    <div class="card-header">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">{{ $title }}</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <form method="POST" action="{{ $action }}" class="form" enctype="multipart/form-data">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="row g-6 mb-8 internal-form-grid">
                <div class="col-md-6">
                    <label for="name" class="required form-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $addOn->name) }}" required class="form-control @error('name') is-invalid @enderror" />
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Slug is generated automatically and preserved on rename.</div>
                </div>
                <div class="col-md-3">
                    <label for="default_price" class="required form-label">Default / reference price</label>
                    <input id="default_price" name="default_price" type="number" min="0" step="0.01" value="{{ old('default_price', $addOn->default_price) }}" required class="form-control @error('default_price') is-invalid @enderror" />
                    @error('default_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Suggested default; product assignment can override selling price.</div>
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
                <div class="col-md-6">
                    <label for="image" class="form-label">Image</label>
                    @php
                        $currentImageUrl = \App\Support\PublicMedia::url(old('image_path', $addOn->image_path));
                    @endphp
                    @if ($currentImageUrl)
                        <div class="mb-3">
                            <img src="{{ $currentImageUrl }}" alt="{{ $addOn->name ?: 'Add-on image' }}" class="rounded border" style="max-width: 8rem; max-height: 8rem; object-fit: contain; background: #f5f5f5;" />
                        </div>
                        <input type="hidden" name="image_path" value="{{ old('image_path', $addOn->image_path) }}" />
                        <div class="form-check mb-3">
                            <input id="remove_image" name="remove_image" type="checkbox" value="1" class="form-check-input" @checked(old('remove_image')) />
                            <label for="remove_image" class="form-check-label">Remove current image</label>
                        </div>
                    @endif
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="form-control @error('image') is-invalid @enderror" />
                    @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">JPEG / PNG / WebP, max {{ \App\Support\PublicMedia::maxKilobytes() }} KB.</div>
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-8">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $addOn->is_active))>
                <label class="form-check-label" for="is_active">Add-on is active</label>
            </div>

            <div class="alert alert-light border mb-8">
                Ingredient quantity, unit, and cost belong on each product’s Add-ons configuration — not on this catalog record.
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
