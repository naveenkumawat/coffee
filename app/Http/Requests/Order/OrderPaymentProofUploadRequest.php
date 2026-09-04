<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\AbstractRequest;
use App\Models\Order;
use App\Models\User;

class OrderPaymentProofUploadRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();
        /** @var Order|null $order */
        $order = $this->route('order');

        return $user !== null
            && $order instanceof Order
            && $user->can('uploadPaymentProof', $order);
    }

    public function rules(): array
    {
        return [
            'transaction_id' => [
                'required',
                'string',
                'min:6',
                'max:64',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\\-_]*$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_id.required' => 'Enter the UPI Transaction ID / UTR from your payment app.',
            'transaction_id.min' => 'Transaction ID / UTR looks too short.',
            'transaction_id.max' => 'Transaction ID / UTR looks too long.',
            'transaction_id.regex' => 'Transaction ID / UTR may only contain letters, numbers, hyphens, and underscores.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('transaction_id', $this->input('utr'));

        if (! is_string($raw)) {
            return;
        }

        $normalized = preg_replace('/\s+/', '', trim($raw)) ?? '';

        $this->merge([
            'transaction_id' => $normalized,
        ]);
    }
}
