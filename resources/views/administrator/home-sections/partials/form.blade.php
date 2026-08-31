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
                    <label for="title" class="required form-label">Title</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $section->title) }}" required class="form-control @error('title') is-invalid @enderror" />
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Sort Order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $section->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="name" class="form-label">Internal Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $section->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Defaults to title" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="slug" class="form-label">Slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug', $section->slug) }}" class="form-control @error('slug') is-invalid @enderror" placeholder="Auto-generated from title if blank" />
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-8">
                    <label for="subtitle" class="form-label">Subtitle</label>
                    <input id="subtitle" name="subtitle" type="text" value="{{ old('subtitle', $section->subtitle) }}" class="form-control @error('subtitle') is-invalid @enderror" />
                    @error('subtitle')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="max_items" class="form-label">Max Items</label>
                    <input id="max_items" name="max_items" type="number" min="1" max="50" value="{{ old('max_items', $section->max_items) }}" class="form-control @error('max_items') is-invalid @enderror" placeholder="All" />
                    @error('max_items')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-10">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $section->is_active))>
                <label class="form-check-label" for="is_active">Section is active on the customer homepage</label>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                    ['label' => 'Cancel', 'url' => route('administrator.home-sections.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ]" />
            </div>
        </form>
    </div>
</div>
