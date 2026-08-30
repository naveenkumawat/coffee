<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'product_flavour_id' => ['nullable', 'integer', Rule::exists('product_flavours', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'availability' => ['nullable', 'string', Rule::in(['available', 'unavailable'])],
            'featured' => ['nullable', 'string', Rule::in(['featured', 'standard'])],
            'new' => ['nullable', 'string', Rule::in(['new'])],
            'bestseller' => ['nullable', 'string', Rule::in(['bestseller'])],
        ];
    }
}
