@php
    $index = $index ?? 0;
    $item = $item ?? [];
@endphp
<div class="border rounded p-5" data-faq-item>
    <input type="hidden" name="faq_items[{{ $index }}][id]" value="{{ $item['id'] ?? '' }}">
    <div class="row g-4">
        <div class="col-12 col-md-8">
            <label class="form-label">Question</label>
            <input type="text" name="faq_items[{{ $index }}][question]" value="{{ $item['question'] ?? '' }}" class="form-control" maxlength="255">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Sort</label>
            <input type="number" name="faq_items[{{ $index }}][sort_order]" value="{{ $item['sort_order'] ?? 0 }}" class="form-control" min="0" step="1">
        </div>
        <div class="col-6 col-md-2 d-flex align-items-end">
            <input type="hidden" name="faq_items[{{ $index }}][is_active]" value="0">
            <div class="form-check form-switch form-check-custom form-check-solid mb-2">
                <input type="checkbox" name="faq_items[{{ $index }}][is_active]" value="1" class="form-check-input" @checked($item['is_active'] ?? true)>
                <label class="form-check-label">Active</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Answer</label>
            <textarea name="faq_items[{{ $index }}][answer]" rows="4" class="form-control">{{ $item['answer'] ?? '' }}</textarea>
        </div>
        <div class="col-12">
            <x-internal.button label="Remove" type="button" variant="danger" icon="ki-trash" data-faq-remove />
        </div>
    </div>
</div>
