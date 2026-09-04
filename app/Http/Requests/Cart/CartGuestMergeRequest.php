<?php

namespace App\Http\Requests\Cart;

use App\Http\Requests\AbstractRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class CartGuestMergeRequest extends AbstractRequest
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
            'items' => ['present', 'array', 'max:50'],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.add_ons' => ['sometimes', 'array'],
            'items.*.add_ons.*.add_on_id' => ['required', 'integer', Rule::exists('add_ons', 'id')->whereNull('deleted_at')],
            'items.*.add_ons.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.visitor_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'items.*.attribution' => ['nullable', 'array'],
            'items.*.attribution.source_type' => ['required_with:items.*.attribution', 'string', 'in:recommendation,campaign'],
            'items.*.attribution.source_id' => ['nullable', 'integer', 'min:1'],
            'items.*.attribution.request_id' => ['required_with:items.*.attribution', 'string', 'max:80', 'regex:/^[A-Za-z0-9:_\\-]+$/'],
            'items.*.attribution.strategy' => ['nullable', 'string', 'max:64'],
            'items.*.attribution.reason' => ['nullable', 'string', 'max:64'],
            'items.*.attribution.placement' => ['nullable', 'string', 'max:64'],
            'items.*.attribution.context' => ['nullable', 'string', 'max:64'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ];
    }
}
