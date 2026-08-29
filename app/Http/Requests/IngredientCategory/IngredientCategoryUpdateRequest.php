<?php

namespace App\Http\Requests\IngredientCategory;

use App\Http\Requests\AbstractRequest;

class IngredientCategoryUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageIngredients() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
