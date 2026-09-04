{{--
    Shared operator-friendly JSON rule editor.
    Expected vars:
      $fieldName (string) e.g. rules | targeting_rules
      $fieldLabel (string)
      $fieldHelp (string)
      $jsonValue (string)
      $templates (list of templates with key,label,meaning,when_to_use,rules)
      $docsUrl (string|null)
      $docsLabel (string|null)
      $required (bool)
      $rows (int)
--}}
@php
    $fieldName = $fieldName ?? 'rules';
    $fieldLabel = $fieldLabel ?? 'Rules';
    $fieldHelp = $fieldHelp ?? 'Choose a template, then review the generated rules. Advanced JSON is optional.';
    $jsonValue = $jsonValue ?? '{"all":[],"any":[],"exclude":[]}';
    $templates = $templates ?? [];
    $docsUrl = $docsUrl ?? null;
    $docsLabel = $docsLabel ?? 'How targeting rules work';
    $required = $required ?? true;
    $rows = $rows ?? 12;
    $editorId = 'json-editor-'.preg_replace('/[^a-z0-9_]+/i', '-', $fieldName);
@endphp

<div class="json-rule-editor" data-json-rule-editor id="{{ $editorId }}">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <label for="{{ $fieldName }}" class="{{ $required ? 'required ' : '' }}form-label mb-1">{{ $fieldLabel }}</label>
            <div class="text-muted fs-7">{{ $fieldHelp }}</div>
        </div>
        @if ($docsUrl)
            <a href="{{ $docsUrl }}" class="btn btn-sm btn-light" target="_blank" rel="noopener">
                ? {{ $docsLabel }}
            </a>
        @endif
    </div>

    @if ($templates !== [])
        <div class="row g-4 mb-5">
            <div class="col-lg-6">
                <label class="form-label" for="{{ $editorId }}-template">Template</label>
                <select id="{{ $editorId }}-template" class="form-select" data-template-select>
                    <option value="">Keep current rules</option>
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
            <div class="col-lg-6 d-flex align-items-end gap-2 flex-wrap">
                <button type="button" class="btn btn-light-primary" data-insert-template>Use template</button>
                <button type="button" class="btn btn-light" data-copy-example>Copy example</button>
            </div>
        </div>
    @endif

    <div class="mb-3">
        <button
            class="btn btn-sm btn-light"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#{{ $editorId }}-advanced"
            aria-expanded="false"
        >
            Advanced JSON ▸
        </button>
    </div>

    <div class="collapse show" id="{{ $editorId }}-advanced">
        <label for="{{ $fieldName }}" class="form-label visually-hidden">{{ $fieldLabel }} JSON</label>
        <textarea
            id="{{ $fieldName }}"
            name="{{ $fieldName }}"
            rows="{{ $rows }}"
            @if ($required) required @endif
            class="form-control font-monospace @error($fieldName) is-invalid @enderror"
            data-json-textarea
        >{{ $jsonValue }}</textarea>
        @error($fieldName)
            <div class="invalid-feedback d-block">
                {{ str_contains(strtolower($message), 'json') || str_contains(strtolower($message), 'unsupported') || str_contains(strtolower($message), 'rule')
                    ? $message.' Tip: use a template above, or check that each rule has type, op, and value.'
                    : $message }}
            </div>
        @enderror
        <div class="form-text mt-2">
            Rules use groups <code>all</code> (every rule must match), <code>any</code> (at least one), and <code>exclude</code> (must not match).
            Each rule is <code>{"type":"...","op":"eq","value":...}</code>.
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-json-rule-editor]').forEach((root) => {
                const select = root.querySelector('[data-template-select]');
                const meaning = root.querySelector('[data-template-meaning]');
                const textarea = root.querySelector('[data-json-textarea]');
                const insertBtn = root.querySelector('[data-insert-template]');
                const copyBtn = root.querySelector('[data-copy-example]');

                const selectedOption = () => select ? select.options[select.selectedIndex] : null;

                const pretty = (raw) => {
                    try {
                        return JSON.stringify(JSON.parse(raw), null, 2);
                    } catch (e) {
                        return raw;
                    }
                };

                select?.addEventListener('change', () => {
                    const option = selectedOption();
                    if (!option || !option.value) {
                        if (meaning) meaning.textContent = '';
                        return;
                    }
                    if (meaning) {
                        meaning.textContent = (option.dataset.meaning || '') + (option.dataset.when ? ' — When: ' + option.dataset.when : '');
                    }
                });

                insertBtn?.addEventListener('click', () => {
                    const option = selectedOption();
                    if (!option?.value || !textarea) return;
                    textarea.value = pretty(option.dataset.json || '{}');
                });

                copyBtn?.addEventListener('click', async () => {
                    const option = selectedOption();
                    const raw = option?.value ? pretty(option.dataset.json || '{}') : (textarea?.value || '');
                    try {
                        await navigator.clipboard.writeText(raw);
                        copyBtn.textContent = 'Copied';
                        setTimeout(() => { copyBtn.textContent = 'Copy example'; }, 1500);
                    } catch (e) {
                        copyBtn.textContent = 'Copy failed';
                        setTimeout(() => { copyBtn.textContent = 'Copy example'; }, 1500);
                    }
                });
            });
        </script>
    @endpush
@endonce
