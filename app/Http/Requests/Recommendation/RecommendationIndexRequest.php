<?php

namespace App\Http\Requests\Recommendation;

use App\Enums\RecommendationContext;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class RecommendationIndexRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = max(1, (int) config('coffee.behaviour.recommendations.max_limit', 16));

        return [
            'context' => ['required', 'string', Rule::in(RecommendationContext::values())],
            'visitor_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'cart_product_ids' => ['nullable', 'array', 'max:40'],
            'cart_product_ids.*' => ['integer', 'min:1'],
            'exclude_product_ids' => ['nullable', 'array', 'max:40'],
            'exclude_product_ids.*' => ['integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$max],
        ];
    }
}
