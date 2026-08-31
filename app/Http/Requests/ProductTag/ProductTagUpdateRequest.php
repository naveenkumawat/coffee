<?php

namespace App\Http\Requests\ProductTag;

use App\Enums\ProductTagStyle;
use App\Http\Requests\AbstractRequest;
use App\Models\ProductTag;
use Illuminate\Validation\Rule;

class ProductTagUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    public function rules(): array
    {
        /** @var ProductTag $tag */
        $tag = $this->route('product_tag');

        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('product_tags', 'slug')
                    ->ignore($tag->getKey())
                    ->whereNull('deleted_at'),
            ],
            'style_key' => ['required', 'string', Rule::in(array_keys(ProductTagStyle::options()))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
