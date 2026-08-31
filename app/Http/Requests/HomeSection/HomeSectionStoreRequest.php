<?php

namespace App\Http\Requests\HomeSection;

use App\Http\Requests\AbstractRequest;

class HomeSectionStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'max_items' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
