<?php

namespace App\Http\Requests\Cart;

use App\Http\Requests\AbstractRequest;
use App\Models\User;

class CartItemUpdateRequest extends AbstractRequest
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
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
