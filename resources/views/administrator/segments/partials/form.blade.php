@php
    $rulesJson = old('rules', json_encode($segment->rules ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if (is_array($rulesJson)) {
        $rulesJson = json_encode($rulesJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
@endphp

<form method="POST" action="{{ $action }}" class="form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">{{ $title }}</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-6">
                    <label for="name" class="required form-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $segment->name) }}" required maxlength="120" class="form-control @error('name') is-invalid @enderror" />
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="slug" class="form-label">Slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug', $segment->slug) }}" maxlength="140" class="form-control @error('slug') is-invalid @enderror" />
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="status" class="required form-label">Status</label>
                    <select id="status" name="status" required class="form-select @error('status') is-invalid @enderror">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $segment->status?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="actor_scope" class="required form-label">Actor scope</label>
                    <select id="actor_scope" name="actor_scope" required class="form-select @error('actor_scope') is-invalid @enderror">
                        @foreach ($actorOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('actor_scope', $segment->actor_scope?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('actor_scope')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-12">
                    <label for="description" class="form-label">Internal description</label>
                    <textarea id="description" name="description" rows="2" maxlength="2000" class="form-control @error('description') is-invalid @enderror">{{ old('description', $segment->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Rules</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="alert alert-light-info mb-6">
                Use <code>all</code> / <code>any</code> / <code>exclude</code> groups with allowlisted types:
                {{ implode(', ', array_keys($ruleTypeOptions)) }}.
                Operators: {{ implode(', ', array_keys($operatorOptions)) }}.
                Thresholds (e.g. completed_orders, last_purchase_days, orders_per_30d) are configured per segment — no hardcoded business defaults.
                Segments do not nest other segments.
            </div>
            @if ($segment->exists)
                <div class="mb-4 text-muted fs-7">
                    Summary: {{ $segment->ruleSummary() }}
                </div>
            @endif
            <label for="rules" class="required form-label">Rule definition (JSON)</label>
            <textarea id="rules" name="rules" rows="14" required class="form-control font-monospace @error('rules') is-invalid @enderror">{{ $rulesJson }}</textarea>
            @error('rules')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="d-flex justify-content-end gap-3 mb-10">
        <a href="{{ route('administrator.segments.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $submit }}</button>
    </div>
</form>

@if (! empty($showPreview) && $segment->exists)
    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Safe preview</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <p class="text-muted">Test against one actor or run a capped approximate customer count. No PII dump.</p>
            <form method="POST" action="{{ route('administrator.segments.preview', $segment) }}" class="row g-4 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label for="customer_id" class="form-label">Customer id</label>
                    <input id="customer_id" name="customer_id" type="number" min="1" class="form-control @error('customer_id') is-invalid @enderror" />
                    @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="visitor_key" class="form-label">Visitor key</label>
                    <input id="visitor_key" name="visitor_key" type="text" maxlength="64" class="form-control @error('visitor_key') is-invalid @enderror" />
                    @error('visitor_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-8">
                        <input class="form-check-input" type="checkbox" value="1" id="run_count" name="run_count">
                        <label class="form-check-label" for="run_count">Approx. customer count (capped)</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-light-primary w-100">Preview</button>
                </div>
            </form>
            @error('preview')<div class="text-danger mt-3">{{ $message }}</div>@enderror
        </div>
    </div>
@endif
