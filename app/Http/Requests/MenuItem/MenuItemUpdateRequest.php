<?php

namespace App\Http\Requests\MenuItem;

use App\Http\Requests\AbstractRequest;
use App\Models\MenuItem;
use Illuminate\Validation\Rule;

class MenuItemUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        $menuItem = $this->route('menuItem');

        return $menuItem instanceof MenuItem
            ? ($this->user('admin')?->can('update', $menuItem) ?? false)
            : false;
    }

    public function rules(): array
    {
        /** @var MenuItem $menuItem */
        $menuItem = $this->route('menuItem');

        return [
            'menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('menu_items', 'slug')->ignore($menuItem)],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_available' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }
}
