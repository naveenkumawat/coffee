<?php

namespace App\Http\Requests\Ingredient;

use App\Enums\IngredientUnit;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class IngredientIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'ingredient_category_id' => ['nullable', 'integer', Rule::exists('ingredient_categories', 'id')],
            'ingredient_brand_id' => ['nullable', 'integer', Rule::exists('ingredient_brands', 'id')->whereNull('deleted_at')],
            'measurement_unit' => ['nullable', 'string', Rule::in(array_keys(IngredientUnit::options()))],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
