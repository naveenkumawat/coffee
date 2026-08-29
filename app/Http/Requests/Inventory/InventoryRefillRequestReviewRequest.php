<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\AbstractRequest;

class InventoryRefillRequestReviewRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageIngredients() ?? false;
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string'],
        ];
    }
}
