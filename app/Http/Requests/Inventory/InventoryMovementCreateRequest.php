<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryTransactionType;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class InventoryMovementCreateRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'ingredient_id' => ['nullable', 'integer', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'inventory_refill_request_id' => ['nullable', 'integer', Rule::exists('inventory_refill_requests', 'id')],
            'transaction_type' => ['nullable', 'string', Rule::in(array_keys(InventoryTransactionType::mutationOptions()))],
        ];
    }
}
