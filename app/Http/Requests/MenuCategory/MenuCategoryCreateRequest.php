<?php

namespace App\Http\Requests\MenuCategory;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class MenuCategoryCreateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageMenuCatalog() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('menu_categories', 'slug')],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
