<?php

namespace App\Http\Requests\Home;

use App\Enums\HomeSectionPlacement;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class HomeShowRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'placement' => ['nullable', 'string', Rule::in(HomeSectionPlacement::values())],
            'visitor_key' => ['nullable', 'string', 'max:64'],
            'session_key' => ['nullable', 'string', 'max:64'],
            'fulfilment_method' => ['nullable', 'string', 'max:40'],
            'cart_product_ids' => ['nullable', 'array', 'max:40'],
            'cart_product_ids.*' => ['integer', 'min:1'],
        ];
    }
}
