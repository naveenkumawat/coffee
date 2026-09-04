@php
    $startsLocal = old('starts_at', $campaign->starts_at?->format('Y-m-d\TH:i'));
    $endsLocal = old('ends_at', $campaign->ends_at?->format('Y-m-d\TH:i'));
    $placementJson = old('placement_rules', json_encode($campaign->placement_rules ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $targetingJson = old('targeting_rules', json_encode($campaign->targeting_rules ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $triggerJson = old('trigger_rules', json_encode($campaign->trigger_rules ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if (is_array($placementJson)) {
        $placementJson = json_encode($placementJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    if (is_array($targetingJson)) {
        $targetingJson = json_encode($targetingJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    if (is_array($triggerJson)) {
        $triggerJson = json_encode($triggerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">{{ $title }} — Content</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-6">
                    <label for="name" class="required form-label">Internal name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $campaign->name) }}" required maxlength="120" class="form-control @error('name') is-invalid @enderror" />
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="internal_label" class="form-label">Internal label</label>
                    <input id="internal_label" name="internal_label" type="text" value="{{ old('internal_label', $campaign->internal_label) }}" maxlength="120" class="form-control @error('internal_label') is-invalid @enderror" />
                    @error('internal_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="status" class="required form-label">Status</label>
                    <select id="status" name="status" required class="form-select @error('status') is-invalid @enderror">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $campaign->status?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="surface" class="required form-label">Surface</label>
                    <select id="surface" name="surface" required class="form-select @error('surface') is-invalid @enderror">
                        @foreach ($surfaceOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('surface', $campaign->surface?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('surface')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="priority" class="form-label">Priority</label>
                    <input id="priority" name="priority" type="number" min="0" max="10000" value="{{ old('priority', $campaign->priority) }}" class="form-control @error('priority') is-invalid @enderror" />
                    @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-12">
                    <label for="title" class="required form-label">Customer title</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $campaign->title) }}" required maxlength="160" class="form-control @error('title') is-invalid @enderror" />
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-12">
                    <label for="message" class="form-label">Message</label>
                    <textarea id="message" name="message" rows="3" maxlength="2000" class="form-control @error('message') is-invalid @enderror">{{ old('message', $campaign->message) }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="image" class="form-label">Image</label>
                    <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control @error('image') is-invalid @enderror" />
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if ($campaign->image_path)
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" value="1" id="remove_image" name="remove_image">
                            <label class="form-check-label" for="remove_image">Remove current image</label>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">CTA, schedule &amp; frequency</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-6 internal-form-grid">
                <div class="col-md-4">
                    <label for="cta_type" class="required form-label">CTA type</label>
                    <select id="cta_type" name="cta_type" required class="form-select @error('cta_type') is-invalid @enderror">
                        @foreach ($ctaTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('cta_type', $campaign->cta_type?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('cta_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="cta_label" class="form-label">CTA label</label>
                    <input id="cta_label" name="cta_label" type="text" value="{{ old('cta_label', $campaign->cta_label) }}" maxlength="80" class="form-control @error('cta_label') is-invalid @enderror" />
                    @error('cta_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="cta_internal_path" class="form-label">Internal path</label>
                    <input id="cta_internal_path" name="cta_internal_path" type="text" value="{{ old('cta_internal_path', $campaign->cta_internal_path) }}" placeholder="/menu" class="form-control @error('cta_internal_path') is-invalid @enderror" />
                    @error('cta_internal_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="cta_product_id" class="form-label">CTA product</label>
                    <select id="cta_product_id" name="cta_product_id" class="form-select @error('cta_product_id') is-invalid @enderror" data-control="select2" data-placeholder="—" data-allow-clear="true">
                        <option value="">—</option>
                        @foreach ($productOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('cta_product_id', $campaign->cta_product_id) === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('cta_product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="cta_category_id" class="form-label">CTA category</label>
                    <select id="cta_category_id" name="cta_category_id" class="form-select @error('cta_category_id') is-invalid @enderror" data-control="select2" data-placeholder="—" data-allow-clear="true">
                        <option value="">—</option>
                        @foreach ($categoryOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('cta_category_id', $campaign->cta_category_id) === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('cta_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="cta_promotion_id" class="form-label">CTA promotion</label>
                    <select id="cta_promotion_id" name="cta_promotion_id" class="form-select @error('cta_promotion_id') is-invalid @enderror" data-control="select2" data-placeholder="—" data-allow-clear="true">
                        <option value="">—</option>
                        @foreach ($promotionOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('cta_promotion_id', $campaign->cta_promotion_id) === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('cta_promotion_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="starts_at" class="form-label">Starts at</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ $startsLocal }}" class="form-control @error('starts_at') is-invalid @enderror" />
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="ends_at" class="form-label">Ends at</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ $endsLocal }}" class="form-control @error('ends_at') is-invalid @enderror" />
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="frequency_policy" class="required form-label">Frequency</label>
                    <select id="frequency_policy" name="frequency_policy" required class="form-select @error('frequency_policy') is-invalid @enderror">
                        @foreach ($frequencyOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('frequency_policy', $campaign->frequency_policy?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('frequency_policy')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="cooldown_hours" class="form-label">Cooldown hours</label>
                    <input id="cooldown_hours" name="cooldown_hours" type="number" min="1" value="{{ old('cooldown_hours', $campaign->cooldown_hours) }}" class="form-control @error('cooldown_hours') is-invalid @enderror" />
                    @error('cooldown_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="max_impressions" class="form-label">Max impressions</label>
                    <input id="max_impressions" name="max_impressions" type="number" min="1" value="{{ old('max_impressions', $campaign->max_impressions) }}" class="form-control @error('max_impressions') is-invalid @enderror" />
                    @error('max_impressions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card internal-form-card mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Placement, targeting &amp; trigger</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="alert alert-light-info mb-6">
                Allowed placements: {{ implode(', ', array_keys($placementOptions)) }}.
                Prefer templates below for targeting, placement, and triggers. Advanced JSON remains available.
                Location rules fail closed when location context is unavailable.
                Reference reusable audiences with <code>segment_matches</code> / <code>segment_not_matches</code> and an active segment id.
            </div>
            <div class="row g-6">
                <div class="col-md-12">
                    @include('internal.partials.json-simple-editor', [
                        'fieldName' => 'placement_rules',
                        'fieldLabel' => 'Where this campaign appears',
                        'fieldHelp' => 'Choose a placement template (home, cart, checkout, …). This controls which customer pages can show the campaign.',
                        'jsonValue' => $placementJson,
                        'templates' => $placementTemplates ?? [],
                        'docsUrl' => route('administrator.documentation.show', 'campaigns'),
                        'rows' => 6,
                    ])
                    @error('placement_rules.placements')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-12">
                    @include('internal.partials.json-rule-editor', [
                        'fieldName' => 'targeting_rules',
                        'fieldLabel' => 'Who should see this campaign',
                        'fieldHelp' => 'Pick an audience template such as guests, logged-in customers, or loyalty near reward. Generated rules stay editable as Advanced JSON.',
                        'jsonValue' => $targetingJson,
                        'templates' => $targetingTemplates ?? [],
                        'docsUrl' => route('administrator.documentation.show', 'campaigns'),
                        'docsLabel' => 'View campaign targeting examples',
                        'rows' => 10,
                    ])
                </div>
                <div class="col-md-12">
                    @include('internal.partials.json-simple-editor', [
                        'fieldName' => 'trigger_rules',
                        'fieldLabel' => 'When it should appear',
                        'fieldHelp' => 'Immediate, delayed, scroll, or after product views — use a template if unsure.',
                        'jsonValue' => $triggerJson,
                        'templates' => $triggerTemplates ?? [],
                        'docsUrl' => route('administrator.documentation.show', 'campaigns'),
                        'rows' => 5,
                    ])
                    @error('trigger_rules.type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-3">
        <a href="{{ route('administrator.campaigns.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $submit }}</button>
    </div>
</form>
