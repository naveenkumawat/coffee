<?php

namespace App\Http\Requests\CafeTable;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class CafeTableUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageWebsiteSettings() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $tableId = $this->route('cafe_table')?->getKey();

        return [
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('cafe_tables', 'code')->whereNull('deleted_at')->ignore($tableId),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
