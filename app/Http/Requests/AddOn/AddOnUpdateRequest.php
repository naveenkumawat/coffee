<?php

namespace App\Http\Requests\AddOn;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class AddOnUpdateRequest extends AbstractRequest
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
            'lines' => ['sometimes', 'array'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.ingredient_id' => ['required', 'integer', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.measurement_unit' => ['required', 'string', 'max:20'],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
