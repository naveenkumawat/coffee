<?php

namespace App\Http\Requests\IngredientBrand;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class IngredientBrandIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
