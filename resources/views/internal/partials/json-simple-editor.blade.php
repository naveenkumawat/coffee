{{--
    Placement / trigger JSON templates for campaigns.
    Vars: $fieldName, $fieldLabel, $fieldHelp, $jsonValue, $templates, $required, $rows, $docsUrl
--}}
@php
    $fieldName = $fieldName ?? 'placement_rules';
    $fieldLabel = $fieldLabel ?? 'Placement rules';
    $fieldHelp = $fieldHelp ?? 'Choose where this campaign can appear.';
    $jsonValue = $jsonValue ?? '{}';
    $templates = $templates ?? [];
    $required = $required ?? true;
    $rows = $rows ?? 6;
    $docsUrl = $docsUrl ?? null;
    $editorId = 'json-simple-'.preg_replace('/[^a-z0-9_]+/i', '-', $fieldName);
@endphp

<div class="json-rule-editor" data-json-rule-editor id="{{ $editorId }}">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <label for="{{ $fieldName }}" class="{{ $required ? 'required ' : '' }}form-label mb-1">{{ $fieldLabel }}</label>
            <div class="text-muted fs-7">{{ $fieldHelp }}</div>
        </div>
        @if ($docsUrl)
            <a href="{{ $docsUrl }}" class="btn btn-sm btn-light" target="_blank" rel="noopener">? See examples</a>
        @endif
    </div>

    @if ($templates !== [])
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <label class="form-label" for="{{ $editorId }}-template">Template</label>
                <select id="{{ $editorId }}-template" class="form-select" data-template-select>
                    <option value="">Keep current value</option>
                    @foreach ($templates as $template)
                        <option
                            value="{{ $template['key'] }}"
                            data-json="{{ e(json_encode($template['rules'], JSON_UNESCAPED_SLASHES)) }}"
                            data-meaning="{{ e($template['meaning']) }}"
                            data-when="{{ e($template['when_to_use']) }}"
                        >{{ $template['label'] }}</option>
                    @endforeach
                </select>
                <div class="form-text" data-template-meaning></div>
            </div>
            <div class="col-lg-5 d-flex align-items-end gap-2 flex-wrap">
                <button type="button" class="btn btn-light-primary" data-insert-template>Use template</button>
                <button type="button" class="btn btn-light" data-copy-example>Copy example</button>
            </div>
        </div>
    @endif

    <div class="mb-3">
        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $editorId }}-advanced" aria-expanded="false">
            Advanced JSON ▸
        </button>
    </div>

    <div class="collapse show" id="{{ $editorId }}-advanced">
        <textarea
            id="{{ $fieldName }}"
            name="{{ $fieldName }}"
            rows="{{ $rows }}"
            @if ($required) required @endif
            class="form-control font-monospace @error($fieldName) is-invalid @enderror"
            data-json-textarea
        >{{ $jsonValue }}</textarea>
        @error($fieldName)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
