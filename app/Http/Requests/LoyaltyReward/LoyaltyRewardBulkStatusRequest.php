<?php

namespace App\Http\Requests\LoyaltyReward;

use App\Enums\LoyaltyRewardStatus;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class LoyaltyRewardBulkStatusRequest extends AbstractRequest
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
            'reward_ids' => ['required', 'array', 'min:1'],
            'reward_ids.*' => ['integer', 'distinct', 'min:1'],
            'status' => ['required', 'string', Rule::in([
                LoyaltyRewardStatus::Active->value,
                LoyaltyRewardStatus::Paused->value,
            ])],
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Confirm the bulk status change before submitting.',
        ];
    }
}
