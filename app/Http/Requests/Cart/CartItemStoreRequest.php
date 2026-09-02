<?php

namespace App\Http\Requests\Cart;

use App\Http\Requests\AbstractRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class CartItemStoreRequest extends AbstractRequest
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
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1'],
            'add_ons' => ['sometimes', 'array'],
            'add_ons.*.add_on_id' => ['required', 'integer', 'distinct', Rule::exists('add_ons', 'id')->whereNull('deleted_at')],
            'add_ons.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
