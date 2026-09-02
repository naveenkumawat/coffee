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
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ];
    }
}
