<?php

namespace App\Http\Requests\Recipe;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class RecipeIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'ingredient_id' => ['nullable', 'integer', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
