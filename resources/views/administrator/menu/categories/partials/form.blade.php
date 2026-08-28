<div class="card card-flush">
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

            <div class="row g-6 mb-8">
                <div class="col-12">
                    <label for="name" class="required form-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $category->name) }}" required class="form-control" />
                </div>
                <div class="col-md-6">
                    <label for="slug" class="form-label">Slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug', $category->slug) }}" class="form-control" />
                </div>
                <div class="col-md-6">
                    <label for="sort_order" class="form-label">Sort Order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order) }}" class="form-control" />
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="4" class="form-control">{{ old('description', $category->description) }}</textarea>
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-10">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $category->is_active))>
                <label class="form-check-label" for="is_active">Category is active on the public menu</label>
            </div>

            <div class="d-flex justify-content-end gap-3">
                <a href="{{ route('administrator.menu.categories.index') }}" class="btn btn-light">Back</a>
                <button type="submit" class="btn btn-primary">{{ $submit }}</button>
            </div>
        </form>
    </div>
</div>
