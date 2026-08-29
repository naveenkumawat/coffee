<?php

namespace App\Http\Requests\Ingredient;

use App\Enums\IngredientUnit;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class IngredientCreateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageIngredients() ?? false;
    }

    public function rules(): array
    {
        return [
            'ingredient_category_id' => ['required', 'integer', Rule::exists('ingredient_categories', 'id')],
            'ingredient_brand_id' => ['nullable', 'integer', Rule::exists('ingredient_brands', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('ingredients', 'slug')],
            'description' => ['nullable', 'string'],
            'measurement_unit' => ['required', 'string', Rule::in(array_keys(IngredientUnit::options()))],
            'purchase_quantity' => ['required', 'decimal:0,3', 'gt:0'],
            'purchase_cost' => ['required', 'decimal:0,2', 'gte:0'],
            'minimum_stock' => ['nullable', 'decimal:0,3', 'gte:0'],
            'reorder_level' => ['nullable', 'decimal:0,3', 'gte:0'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'supplier_email' => ['nullable', 'email', 'max:160'],
            'supplier_phone' => ['nullable', 'string', 'max:40'],
            'supplier_notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
