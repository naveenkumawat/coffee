<?php

namespace App\Http\Requests\ProductFlavour;

use App\Http\Requests\AbstractRequest;
use App\Support\PublicMedia;
use Illuminate\Validation\Rule;

class ProductFlavourCreateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image' => PublicMedia::uploadRules(),
            'remove_image' => ['nullable', 'boolean'],
            'product_category_ids' => ['nullable', 'array'],
            'product_category_ids.*' => ['integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
