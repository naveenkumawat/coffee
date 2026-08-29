<?php

namespace App\Http\Requests\Inventory;

use App\Enums\IngredientUnit;
use App\Enums\InventoryStockStatus;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class InventoryIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'ingredient_category_id' => ['nullable', 'integer', Rule::exists('ingredient_categories', 'id')],
            'ingredient_brand_id' => ['nullable', 'integer', Rule::exists('ingredient_brands', 'id')->whereNull('deleted_at')],
            'measurement_unit' => ['nullable', 'string', Rule::in(array_keys(IngredientUnit::options()))],
            'stock_status' => ['nullable', 'string', Rule::in(array_keys(InventoryStockStatus::options()))],
        ];
    }
}
