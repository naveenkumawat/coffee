<?php

namespace App\Http\Requests\ProductFlavour;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class ProductFlavourIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
        ];
    }
}
