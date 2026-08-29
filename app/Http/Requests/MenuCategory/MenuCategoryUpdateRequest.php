<?php

namespace App\Http\Requests\MenuCategory;

use App\Http\Requests\AbstractRequest;
use App\Models\MenuCategory;
use Illuminate\Validation\Rule;

class MenuCategoryUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        $menuCategory = $this->route('menuCategory');

        return $menuCategory instanceof MenuCategory
            ? ($this->user('admin')?->can('update', $menuCategory) ?? false)
            : false;
    }

    public function rules(): array
    {
        /** @var MenuCategory $menuCategory */
        $menuCategory = $this->route('menuCategory');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('menu_categories', 'slug')->ignore($menuCategory)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
