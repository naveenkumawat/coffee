<?php

namespace App\Http\Requests\Cart;

use App\Enums\OrderFulfilmentMethod;
use App\Http\Requests\AbstractRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class CartLoyaltyRewardRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user?->hasRole('customer') ?? false;
    }

    public function rules(): array
    {
        return [
            'loyalty_reward_id' => ['required', 'integer', 'exists:loyalty_rewards,id'],
            'fulfilment_method' => ['nullable', 'string', Rule::enum(OrderFulfilmentMethod::class)],
        ];
    }
}
