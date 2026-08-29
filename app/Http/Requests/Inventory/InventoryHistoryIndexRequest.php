<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryTransactionType;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class InventoryHistoryIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'ingredient_id' => ['nullable', 'integer', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'ingredient_category_id' => ['nullable', 'integer', Rule::exists('ingredient_categories', 'id')],
            'ingredient_brand_id' => ['nullable', 'integer', Rule::exists('ingredient_brands', 'id')->whereNull('deleted_at')],
            'transaction_type' => ['nullable', 'string', Rule::in(array_keys(InventoryTransactionType::historyOptions()))],
            'created_by' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
