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
                    <label for="code" class="required form-label">Code</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        value="{{ old('code', $table->code) }}"
                        required
                        class="form-control @error('code') is-invalid @enderror"
                        placeholder="T1"
                    />
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Short label shown to customers and staff (e.g. T1, Outdoor 1).</div>
                </div>
                <div class="col-md-6">
                    <label for="name" class="form-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $table->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Optional display name" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="sort_order" class="form-label">Sort order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $table->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-10">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $table->is_active))>
                <label class="form-check-label" for="is_active">Available for new dine-in orders</label>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                    ['label' => 'Cancel', 'url' => route('administrator.cafe-tables.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ]" />
            </div>
        </form>
    </div>
</div>
