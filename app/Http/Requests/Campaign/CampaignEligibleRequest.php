<?php

namespace App\Http\Requests\Campaign;

use App\Enums\CampaignPlacement;
use App\Enums\CampaignSurface;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class CampaignEligibleRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'placement' => ['required', 'string', Rule::in(CampaignPlacement::values())],
            'surface' => ['nullable', 'string', Rule::in(CampaignSurface::values())],
            'visitor_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'session_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'cart_product_ids' => ['nullable', 'array', 'max:40'],
            'cart_product_ids.*' => ['integer', 'min:1'],
            'fulfilment_method' => ['nullable', 'string', 'max:40'],
            'location_city' => ['nullable', 'string', 'max:80'],
            'location_zone' => ['nullable', 'string', 'max:80'],
            'location_available' => ['nullable', 'boolean'],
        ];
    }
}
