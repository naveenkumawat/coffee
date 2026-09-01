<?php

namespace App\Http\Requests\Promotion;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFulfilmentScope;
use App\Enums\PromotionType;
use App\Enums\UserRole;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class PromotionStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageWebsiteSettings() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'stackable' => $this->boolean('stackable'),
            'applies_to_all_products' => $this->boolean('applies_to_all_products', true),
            'applies_to_all_customers' => $this->boolean('applies_to_all_customers', true),
            'first_order_only' => $this->boolean('first_order_only'),
            'weekdays' => array_values(array_filter((array) $this->input('weekdays', []), fn ($day) => $day !== null && $day !== '')),
            'product_ids' => array_values(array_filter((array) $this->input('product_ids', []), fn ($id) => $id !== null && $id !== '')),
            'product_category_ids' => array_values(array_filter((array) $this->input('product_category_ids', []), fn ($id) => $id !== null && $id !== '')),
            'customer_ids' => array_values(array_filter((array) $this->input('customer_ids', []), fn ($id) => $id !== null && $id !== '')),
            'code' => filled($this->input('code')) ? strtoupper(trim((string) $this->input('code'))) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->promotionRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function promotionRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === PromotionType::Coupon->value),
                'nullable',
                'string',
                'max:40',
                'alpha_dash:ascii',
                Rule::unique('promotions', 'code')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'discount_type' => ['required', Rule::enum(PromotionDiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'minimum_subtotal' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'stackable' => ['nullable', 'boolean'],
            'applies_to_all_products' => ['nullable', 'boolean'],
            'applies_to_all_customers' => ['nullable', 'boolean'],
            'first_order_only' => ['nullable', 'boolean'],
            'fulfilment_scope' => ['required', Rule::enum(PromotionFulfilmentScope::class)],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'daily_starts_at' => ['nullable', 'date_format:H:i'],
            'daily_ends_at' => ['nullable', 'date_format:H:i', 'after:daily_starts_at'],
            'customer_message' => ['nullable', 'string', 'max:500'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
            'product_ids' => ['nullable', 'array', Rule::requiredIf(fn (): bool => ! $this->boolean('applies_to_all_products') && blank($this->input('product_category_ids')))],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'product_category_ids' => ['nullable', 'array'],
            'product_category_ids.*' => ['integer', 'distinct', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'customer_ids' => ['nullable', 'array', Rule::requiredIf(fn (): bool => ! $this->boolean('applies_to_all_customers'))],
            'customer_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', UserRole::Customer->value)->whereNull('deleted_at')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_ids.required' => 'Select at least one product or category when the offer does not apply to all products.',
            'customer_ids.required' => 'Select at least one customer when the offer does not apply to all customers.',
            'code.required' => 'A promo code is required for coupon promotions.',
        ];
    }
}
