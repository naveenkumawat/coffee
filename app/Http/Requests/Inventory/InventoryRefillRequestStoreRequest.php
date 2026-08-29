<?php

namespace App\Http\Requests\Inventory;

use App\Enums\IngredientUnit;
use App\Http\Requests\AbstractRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class InventoryRefillRequestStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user('admin');

        return $user?->hasRole('barista') ?? false;
    }

    public function rules(): array
    {
        return [
            'ingredient_id' => ['required', 'integer', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'decimal:0,3', 'gt:0'],
            'measurement_unit' => ['required', 'string', Rule::in(array_keys(IngredientUnit::options()))],
            'notes' => ['nullable', 'string'],
        ];
    }
}
