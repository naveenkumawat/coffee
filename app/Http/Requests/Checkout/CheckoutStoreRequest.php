<?php

namespace App\Http\Requests\Checkout;

use App\Enums\OrderFulfilmentMethod;
use App\Http\Requests\AbstractRequest;
use App\Models\User;
use App\Services\Payment\PaymentEligibilityServiceInterface;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user?->hasRole('customer') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('payment_method')) {
            $this->merge(['payment_method' => 'manual_upi']);
        }

        $this->merge([
            'save_delivery_address' => $this->boolean('save_delivery_address'),
            'make_default_address' => $this->boolean('make_default_address'),
        ]);
    }

    public function rules(): array
    {
        $method = (string) $this->input('fulfilment_method');
        $isTakeaway = $method === OrderFulfilmentMethod::Takeaway->value;
        $isDelivery = $method === OrderFulfilmentMethod::Delivery->value;
        $hasSavedAddress = filled($this->input('delivery_address_id'));
        $hasStructured = filled($this->input('address_line_1'));

        return [
            'checkout_token' => ['required', 'string', 'max:64'],
            'fulfilment_method' => [
                'required',
                'string',
                Rule::in([
                    OrderFulfilmentMethod::Takeaway->value,
                    OrderFulfilmentMethod::Delivery->value,
                ]),
            ],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email:rfc', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'pickup_name' => [$isTakeaway ? 'required' : 'nullable', 'string', 'max:255'],
            'pickup_phone' => [$isTakeaway ? 'required' : 'nullable', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string'],
            'pickup_notes' => ['nullable', 'string'],
            'delivery_address_id' => [
                $isDelivery ? 'nullable' : 'prohibited',
                'integer',
                Rule::exists('customer_delivery_addresses', 'id')->where(function ($query): void {
                    $query->where('customer_id', $this->user()?->getKey())->whereNull('deleted_at');
                }),
            ],
            'delivery_address' => [
                $isDelivery && ! $hasSavedAddress && ! $hasStructured ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
            'delivery_phone' => [
                $isDelivery && ! $hasSavedAddress ? 'required' : 'nullable',
                'string',
                'max:50',
            ],
            'delivery_contact_name' => ['nullable', 'string', 'max:255'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
            'address_label' => ['nullable', 'string', 'max:80'],
            'address_line_1' => [
                $isDelivery && ! $hasSavedAddress && ! filled($this->input('delivery_address')) ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city' => [
                $isDelivery && ! $hasSavedAddress && filled($this->input('address_line_1')) ? 'required' : 'nullable',
                'string',
                'max:120',
            ],
            'state' => [
                $isDelivery && ! $hasSavedAddress && filled($this->input('address_line_1')) ? 'required' : 'nullable',
                'string',
                'max:120',
            ],
            'postal_code' => [
                $isDelivery && ! $hasSavedAddress && filled($this->input('address_line_1')) ? 'required' : 'nullable',
                'string',
                'max:20',
            ],
            'save_delivery_address' => ['nullable', 'boolean'],
            'make_default_address' => ['nullable', 'boolean'],
            'cafe_table_id' => ['prohibited'],
            'payment_method' => ['required', 'string', Rule::in(['manual_upi', 'manual', 'cash'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('fulfilment_method') === OrderFulfilmentMethod::DineIn->value) {
                $validator->errors()->add(
                    'fulfilment_method',
                    'Dine-in is no longer available through checkout. Use Dining to start a table session.',
                );
            }

            if (
                (string) $this->input('fulfilment_method') === OrderFulfilmentMethod::Delivery->value
                && $this->boolean('make_default_address')
                && ! $this->boolean('save_delivery_address')
                && ! filled($this->input('delivery_address_id'))
            ) {
                $validator->errors()->add(
                    'make_default_address',
                    'Make default is only available when saving an address or selecting a saved address.',
                );
            }

            /** @var User|null $user */
            $user = $this->user();
            if ($user === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $eligible = app(PaymentEligibilityServiceInterface::class);
            if (! $eligible->isAllowed($user, $this->input('fulfilment_method'), $this->input('payment_method'))) {
                $validator->errors()->add('payment_method', 'The selected payment method is not available for this order.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'delivery_address.required' => 'A delivery address is required for delivery orders.',
            'delivery_phone.required' => 'A contact phone is required for delivery orders.',
            'fulfilment_method.in' => 'Choose Takeaway or Delivery. Dining uses the Dining flow.',
            'cafe_table_id.prohibited' => 'Table selection is only available in Dining.',
        ];
    }
}
