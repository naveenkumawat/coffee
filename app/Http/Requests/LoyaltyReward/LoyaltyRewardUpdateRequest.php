<?php

namespace App\Http\Requests\LoyaltyReward;

class LoyaltyRewardUpdateRequest extends LoyaltyRewardStoreRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->rewardRules();
    }
}
