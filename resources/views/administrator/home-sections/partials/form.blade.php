@php
    $targetingJson = old(
        'targeting_rules',
        json_encode($section->targeting_rules ?? ['all' => [], 'any' => [], 'exclude' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    );
    $placementValue = old('placement', $section->placement?->value ?? $section->placement ?? 'home');
    $sourceTypeValue = old('source_type', $section->source_type?->value ?? $section->source_type ?? 'curated');
@endphp

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
                <div class="col-md-4">
                    <label for="placement" class="required form-label">Placement</label>
                    <select id="placement" name="placement" class="form-select @error('placement') is-invalid @enderror" required>
                        @foreach ($placementOptions as $value => $label)
                            <option value="{{ $value }}" @selected($placementValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('placement')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="source_type" class="required form-label">Source / type</label>
                    <select id="source_type" name="source_type" class="form-select @error('source_type') is-invalid @enderror" required>
                        @foreach ($sourceTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($sourceTypeValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('source_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="priority" class="form-label">Priority</label>
                    <input id="priority" name="priority" type="number" min="0" max="10000" value="{{ old('priority', $section->priority ?? 0) }}" class="form-control @error('priority') is-invalid @enderror" />
                    @error('priority')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Higher priority wins when sections compete.</div>
                </div>
                <div class="col-md-4">
                    <label for="source_category_id" class="form-label">Source category</label>
                    <select id="source_category_id" name="source_category_id" class="form-select @error('source_category_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach ($categoryOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('source_category_id', $section->source_category_id) === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('source_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="source_tag_id" class="form-label">Source tag</label>
                    <select id="source_tag_id" name="source_tag_id" class="form-select @error('source_tag_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach ($tagOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('source_tag_id', $section->source_tag_id) === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('source_tag_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="recommendation_context" class="form-label">Recommendation context</label>
                    <select id="recommendation_context" name="recommendation_context" class="form-select @error('recommendation_context') is-invalid @enderror">
                        <option value="">Default for placement</option>
                        @foreach ($recommendationContextOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('recommendation_context', $section->recommendation_context) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('recommendation_context')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="starts_at" class="form-label">Starts at</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', optional($section->starts_at)->format('Y-m-d\\TH:i')) }}" class="form-control @error('starts_at') is-invalid @enderror" />
                    @error('starts_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="ends_at" class="form-label">Ends at</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', optional($section->ends_at)->format('Y-m-d\\TH:i')) }}" class="form-control @error('ends_at') is-invalid @enderror" />
                    @error('ends_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    @include('internal.partials.json-rule-editor', [
                        'fieldName' => 'targeting_rules',
                        'fieldLabel' => 'Audience targeting',
                        'fieldHelp' => 'Optional. Empty all/any/exclude means everyone. Prefer a template for guests, loyalty, or affinity audiences.',
                        'jsonValue' => is_array($targetingJson) ? json_encode($targetingJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $targetingJson,
                        'templates' => $targetingTemplates ?? [],
                        'docsUrl' => route('administrator.documentation.show', 'homepage-merchandising'),
                        'docsLabel' => 'Homepage targeting help',
                        'required' => false,
                        'rows' => 8,
                    ])
                </div>
            </div>

            <div class="form-check form-switch form-check-custom form-check-solid mb-4">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $section->is_active))>
                <label class="form-check-label" for="is_active">Section is active on the selected placement</label>
            </div>
            <div class="form-check form-switch form-check-custom form-check-solid mb-4">
                <input type="hidden" name="dedupe_products" value="0">
                <input class="form-check-input" type="checkbox" id="dedupe_products" name="dedupe_products" value="1" @checked(old('dedupe_products', $section->dedupe_products ?? true))>
                <label class="form-check-label" for="dedupe_products">Deduplicate products already shown in earlier sections</label>
            </div>
            <div class="form-check form-switch form-check-custom form-check-solid mb-10">
                <input type="hidden" name="fallback_to_curated" value="0">
                <input class="form-check-input" type="checkbox" id="fallback_to_curated" name="fallback_to_curated" value="1" @checked(old('fallback_to_curated', $section->fallback_to_curated ?? true))>
                <label class="form-check-label" for="fallback_to_curated">Fall back to curated products when the intelligent source is empty</label>
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
