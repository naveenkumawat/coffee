<?php

namespace App\Http\Requests\Cart;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class CartReferralRewardRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reward_id' => ['sometimes', 'integer', 'min:1'],
            'referral_coupon' => ['sometimes', 'string', 'max:64'],
            'fulfilment_method' => ['nullable', 'string', Rule::in(['takeaway', 'delivery', 'dine_in'])],
        ];
    }
}
