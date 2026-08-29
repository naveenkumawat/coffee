<?php

namespace App\Http\Requests\Product;

use Illuminate\Validation\Rule;

class ProductUpdateRequest extends ProductCreateRequest
{
    public function rules(): array
    {
        $product = $this->route('product');

        return array_merge(parent::rules(), [
            'sku' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('products', 'sku')
                    ->ignore($product?->getKey())
                    ->whereNull('deleted_at'),
            ],
        ]);
    }
}
