<?php

namespace App\Http\Requests\Checkout;

use App\Enums\OrderFulfilmentMethod;
use App\Http\Requests\AbstractRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

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
        $isDelivery = $this->input('fulfilment_method') === OrderFulfilmentMethod::Delivery->value;

        return [
            'checkout_token' => ['required', 'string', 'max:64'],
            'fulfilment_method' => ['required', 'string', Rule::enum(OrderFulfilmentMethod::class)],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email:rfc', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'pickup_name' => [$isDelivery ? 'nullable' : 'required', 'string', 'max:255'],
            'pickup_phone' => [$isDelivery ? 'nullable' : 'required', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string'],
            'pickup_notes' => ['nullable', 'string'],
            'delivery_address' => [$isDelivery ? 'required' : 'nullable', 'string', 'max:2000'],
            'delivery_phone' => [$isDelivery ? 'required' : 'nullable', 'string', 'max:50'],
            'delivery_contact_name' => ['nullable', 'string', 'max:255'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_address.required' => 'A delivery address is required for delivery orders.',
            'delivery_phone.required' => 'A contact phone is required for delivery orders.',
        ];
    }
}
