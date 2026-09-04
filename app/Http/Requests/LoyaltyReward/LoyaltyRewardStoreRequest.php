<?php

namespace App\Http\Requests\LoyaltyReward;

use App\Enums\LoyaltyRewardStatus;
use App\Enums\LoyaltyRewardType;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class LoyaltyRewardStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageWebsiteSettings() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'product_ids' => array_values(array_filter((array) $this->input('product_ids', []), fn ($id) => $id !== null && $id !== '')),
            'product_category_ids' => array_values(array_filter((array) $this->input('product_category_ids', []), fn ($id) => $id !== null && $id !== '')),
            'add_on_ids' => array_values(array_filter((array) $this->input('add_on_ids', []), fn ($id) => $id !== null && $id !== '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->rewardRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rewardRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(LoyaltyRewardStatus::class)],
            'reward_type' => ['required', Rule::enum(LoyaltyRewardType::class)],
            'points_cost' => ['required', 'integer', 'min:1'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'maximum_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'minimum_spend' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer_period_days' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer'],
            'customer_description' => ['nullable', 'string', 'max:500'],
            'internal_note' => ['nullable', 'string', 'max:500'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'product_category_ids' => ['nullable', 'array'],
            'product_category_ids.*' => ['integer', 'exists:product_categories,id'],
            'add_on_ids' => ['nullable', 'array'],
            'add_on_ids.*' => ['integer', 'exists:add_ons,id'],
        ];
    }
}
