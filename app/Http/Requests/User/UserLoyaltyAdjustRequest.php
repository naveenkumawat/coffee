<?php

namespace App\Http\Requests\User;

use App\Http\Requests\AbstractRequest;

class UserLoyaltyAdjustRequest extends AbstractRequest
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
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Confirm this loyalty adjustment before submitting.',
            'idempotency_key.required' => 'A unique submission key is required.',
        ];
    }
}
