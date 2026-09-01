<?php

namespace App\Http\Requests\CafeSchedule;

use Illuminate\Foundation\Http\FormRequest;

class CafeOrderingCloseRequest extends FormRequest
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
            'mode' => ['required', 'in:indefinite,until'],
            'closed_until' => ['nullable', 'required_if:mode,until', 'date', 'after:now'],
            'customer_message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
