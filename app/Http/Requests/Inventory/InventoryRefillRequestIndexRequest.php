<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryRefillRequestStatus;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class InventoryRefillRequestIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'ingredient_id' => ['nullable', 'integer', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', 'string', Rule::in(array_keys(InventoryRefillRequestStatus::options()))],
            'requested_by' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ];
    }
}
