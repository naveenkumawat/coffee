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
                    <label for="platform_key" class="required form-label">Platform key</label>
                    <input
                        id="platform_key"
                        name="platform_key"
                        type="text"
                        value="{{ old('platform_key', $link->platform_key) }}"
                        required
                        class="form-control @error('platform_key') is-invalid @enderror"
                        placeholder="facebook"
                        @disabled($link->exists)
                    />
                    @if ($link->exists)
                        <input type="hidden" name="platform_key" value="{{ old('platform_key', $link->platform_key) }}" />
                    @endif
                    @error('platform_key')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Unique lowercase key (e.g. facebook). Cannot change after create.</div>
                </div>
                <div class="col-md-6">
                    <label for="label" class="required form-label">Label</label>
                    <input id="label" name="label" type="text" value="{{ old('label', $link->label) }}" required class="form-control @error('label') is-invalid @enderror" />
                    @error('label')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="icon_key" class="required form-label">Icon</label>
                    <select id="icon_key" name="icon_key" required class="form-select @error('icon_key') is-invalid @enderror">
                        @foreach ($iconOptions as $value => $optionLabel)
                            <option value="{{ $value }}" @selected((string) old('icon_key', $link->icon_key) === (string) $value)>{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                    @error('icon_key')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="sort_order" class="form-label">Sort order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $link->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" />
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="url" class="form-label">URL</label>
                    <input id="url" name="url" type="url" value="{{ old('url', $link->url) }}" class="form-control @error('url') is-invalid @enderror" placeholder="https://" />
                    @error('url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Leave blank to hide the link (except WhatsApp, which can use the Website Settings WhatsApp number).
                    </div>
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-10">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $link->is_active))>
                <label class="form-check-label" for="is_active">Show on customer storefront when URL is available</label>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <x-internal.button-group :items="[
                    ['label' => $submit, 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-check'],
                    ['label' => 'Cancel', 'url' => route('administrator.social-links.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
                ]" />
            </div>
        </form>
    </div>
</div>
