<?php

namespace App\Http\Requests\AddOn;

use App\Http\Requests\AbstractRequest;
use App\Support\PublicMedia;

class AddOnStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image' => PublicMedia::uploadRules(),
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
