<?php

namespace App\Http\Requests\Checkout;

use App\Http\Requests\AbstractRequest;
use App\Models\User;

class CheckoutStoreRequest extends AbstractRequest
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
            'checkout_token' => ['required', 'string', 'max:64'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email:rfc', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'pickup_name' => ['required', 'string', 'max:255'],
            'pickup_phone' => ['required', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string'],
            'pickup_notes' => ['nullable', 'string'],
        ];
    }
}
