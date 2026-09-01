<?php

namespace App\Http\Requests\CafeSchedule;

use App\Enums\CafeClosureType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CafeClosureStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageWebsiteSettings() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(CafeClosureType::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'customer_message' => ['nullable', 'string', 'max:500'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
