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
                <div class="col-md-6">
                    <label for="menu_category_id" class="required form-label">Category</label>
                    <select id="menu_category_id" name="menu_category_id" required class="form-select" data-control="select2" data-placeholder="Select a category">
                        <option></option>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}" @selected(old('menu_category_id', $item->menu_category_id) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="price" class="required form-label">Price</label>
                    <input id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $item->price) }}" required class="form-control" />
                </div>
                <div class="col-12">
                    <label for="name" class="required form-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $item->name) }}" required class="form-control" />
                </div>
                <div class="col-md-6">
                    <label for="slug" class="form-label">Slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug', $item->slug) }}" class="form-control" />
                </div>
                <div class="col-md-6">
                    <label for="sort_order" class="form-label">Sort Order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $item->sort_order) }}" class="form-control" />
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="4" class="form-control">{{ old('description', $item->description) }}</textarea>
                </div>
            </div>

            <div class="row g-6 mb-10">
                <div class="col-md-6">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="is_available" value="0">
                        <input class="form-check-input" type="checkbox" id="is_available" name="is_available" value="1" @checked(old('is_available', $item->is_available))>
                        <label class="form-check-label" for="is_available">Available on the public menu</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="is_featured" value="0">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" @checked(old('is_featured', $item->is_featured))>
                        <label class="form-check-label" for="is_featured">Highlight as featured</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3">
                <a href="{{ route('administrator.menu.items.index') }}" class="btn btn-light">Back</a>
                <button type="submit" class="btn btn-primary">{{ $submit }}</button>
            </div>
        </form>
    </div>
</div>
