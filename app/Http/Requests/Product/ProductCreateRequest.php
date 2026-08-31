<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductServingUnit;
use App\Http\Requests\AbstractRequest;
use App\Support\PublicMedia;
use Illuminate\Validation\Rule;

class ProductCreateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    public function rules(): array
    {
        return [
            'product_category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->whereNull('deleted_at')],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'customer_ingredient_summary' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image' => PublicMedia::uploadRules(),
            'remove_image' => ['nullable', 'boolean'],
            'preparation_time_minutes' => ['nullable', 'integer', 'min:0', 'max:999'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'product_flavour_ids' => ['nullable', 'array'],
            'product_flavour_ids.*' => ['integer', Rule::exists('product_flavours', 'id')->whereNull('deleted_at')],
            'product_tag_ids' => ['nullable', 'array'],
            'product_tag_ids.*' => ['integer', Rule::exists('product_tags', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'is_active' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'is_vegetarian' => ['nullable', 'boolean'],
            'is_customizable' => ['nullable', 'boolean'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:120'],
            'variants.*.serving_size_value' => ['required', 'decimal:0,3', 'gt:0'],
            'variants.*.serving_size_unit' => ['required', 'string', Rule::in(array_keys(ProductServingUnit::options()))],
            'variants.*.price' => ['required', 'decimal:0,2', 'gte:0'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.is_available' => ['nullable', 'boolean'],
        ];
    }
}
