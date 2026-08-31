<?php

namespace App\Http\Requests\Checkout;

use App\Enums\OrderFulfilmentMethod;
use App\Http\Requests\AbstractRequest;
use App\Models\User;
use App\Services\Payment\PaymentEligibilityServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
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
    }

    public function rules(): array
    {
        $method = (string) $this->input('fulfilment_method');
        $isTakeaway = $method === OrderFulfilmentMethod::Takeaway->value;
        $isDelivery = $method === OrderFulfilmentMethod::Delivery->value;
        $isDineIn = $method === OrderFulfilmentMethod::DineIn->value;

        return [
            'checkout_token' => ['required', 'string', 'max:64'],
            'fulfilment_method' => ['required', 'string', Rule::enum(OrderFulfilmentMethod::class)],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email:rfc', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'pickup_name' => [$isTakeaway ? 'required' : 'nullable', 'string', 'max:255'],
            'pickup_phone' => [$isTakeaway ? 'required' : 'nullable', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string'],
            'pickup_notes' => ['nullable', 'string'],
            'delivery_address' => [$isDelivery ? 'required' : 'nullable', 'string', 'max:2000'],
            'delivery_phone' => [$isDelivery ? 'required' : 'nullable', 'string', 'max:50'],
            'delivery_contact_name' => ['nullable', 'string', 'max:255'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
            'cafe_table_id' => [
                $isDineIn ? 'required' : 'nullable',
                'integer',
                Rule::exists('cafe_tables', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'payment_method' => ['required', 'string', Rule::in(['manual_upi', 'manual', 'cash'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('fulfilment_method') === OrderFulfilmentMethod::DineIn->value
                && ! app(WebsiteSettingServiceInterface::class)->dineInEnabled()) {
                $validator->errors()->add('fulfilment_method', 'Dine-in ordering is not available.');
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
            'cafe_table_id.required' => 'Please select your table for dine-in.',
            'cafe_table_id.exists' => 'Selected table is not available.',
        ];
    }
}
