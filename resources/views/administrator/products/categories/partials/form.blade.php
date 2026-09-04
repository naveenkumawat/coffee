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
                <div class="col-md-8">
                    <label for="name" class="required form-label">Category Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $category->name) }}" required class="form-control @error('name') is-invalid @enderror" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Slug is generated automatically and preserved on rename.</div>
                </div>
                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Sort Order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $category->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="image" class="form-label">Category image</label>
                    @php
                        $currentImageUrl = \App\Support\PublicMedia::url(old('image_path', $category->image_path));
                    @endphp
                    @if ($currentImageUrl)
                        <div class="mb-3">
                            <img src="{{ $currentImageUrl }}" alt="{{ $category->name ?: 'Category image' }}" class="rounded border" style="max-width: 8rem; max-height: 8rem; object-fit: contain; background: #f5f5f5;" />
                        </div>
                        <input type="hidden" name="image_path" value="{{ old('image_path', $category->image_path) }}" />
                        <div class="form-check mb-3">
                            <input id="remove_image" name="remove_image" type="checkbox" value="1" class="form-check-input" @checked(old('remove_image')) />
                            <label for="remove_image" class="form-check-label">Remove current image</label>
                        </div>
                    @endif
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="form-control @error('image') is-invalid @enderror" />
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">JPEG / PNG / WebP, max {{ \App\Support\PublicMedia::maxKilobytes() }} KB.</div>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $category->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-10">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $category->is_active))>
                <label class="form-check-label" for="is_active">Category is active for product assignment</label>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                    ['label' => 'Cancel', 'url' => route('administrator.products.categories.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ]" />
            </div>
        </form>
    </div>
</div>
