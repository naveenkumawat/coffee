<?php

namespace App\Http\Requests\Recipe;

use App\Enums\IngredientUnit;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class RecipeCreateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'preparation_notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.ingredient_id' => [
                'nullable',
                'integer',
                'required_with:lines.*.quantity',
                Rule::exists('ingredients', 'id')->whereNull('deleted_at'),
            ],
            'lines.*.quantity' => ['nullable', 'decimal:0,3', 'gt:0', 'required_with:lines.*.ingredient_id'],
            'lines.*.measurement_unit' => ['nullable', 'string', 'required_with:lines.*.ingredient_id', Rule::in(array_keys(IngredientUnit::options()))],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
