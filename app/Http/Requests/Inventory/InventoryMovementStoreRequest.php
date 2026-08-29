<?php

namespace App\Http\Requests\Inventory;

use App\Enums\IngredientUnit;
use App\Enums\InventoryTransactionType;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class InventoryMovementStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageIngredients() ?? false;
    }

    public function rules(): array
    {
        return [
            'ingredient_id' => ['required', 'integer', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'inventory_refill_request_id' => ['nullable', 'integer', Rule::exists('inventory_refill_requests', 'id')],
            'transaction_type' => ['required', 'string', Rule::in(array_keys(InventoryTransactionType::mutationOptions()))],
            'quantity' => ['required', 'decimal:0,3', 'gte:0'],
            'measurement_unit' => ['required', 'string', Rule::in(array_keys(IngredientUnit::options()))],
            'reference_type' => ['nullable', 'string', 'max:120'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
