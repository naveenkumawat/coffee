<?php

namespace App\Http\Requests\Behaviour;

use App\Enums\BehaviourEventType;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class StoreBehaviourEventRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', Rule::in(BehaviourEventType::clientIngestibleValues())],
            'visitor_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'product_category_id' => ['nullable', 'integer', 'min:1'],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
            'page_context' => ['nullable', 'string', 'max:160'],
            'metadata' => ['nullable', 'array'],
            'metadata.query' => ['nullable', 'string', 'max:200'],
            'metadata.result_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'metadata.quantity' => ['nullable', 'integer', 'min:0', 'max:99'],
            'metadata.variant_id' => ['nullable', 'integer', 'min:1'],
            'metadata.addon_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'metadata.addon_ids' => ['nullable', 'array', 'max:20'],
            'metadata.addon_ids.*' => ['integer', 'min:1'],
            'metadata.item_count' => ['nullable', 'integer', 'min:0', 'max:200'],
            'metadata.fulfilment_method' => ['nullable', 'string', 'max:40'],
            'metadata.source' => ['nullable', 'string', 'max:40'],
            'occurred_at' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9:._-]+$/'],
        ];
    }
}
