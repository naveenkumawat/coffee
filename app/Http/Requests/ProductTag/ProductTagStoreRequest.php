<?php

namespace App\Http\Requests\ProductTag;

use App\Enums\ProductTagStyle;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class ProductTagStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'style_key' => ['required', 'string', Rule::in(array_keys(ProductTagStyle::options()))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
