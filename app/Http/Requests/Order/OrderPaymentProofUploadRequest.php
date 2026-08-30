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
        $maxKb = max(100, (int) config('coffee.payments.proof_max_kilobytes', 5120));

        return [
            'payment_proof' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,gif',
                'max:'.$maxKb,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_proof.required' => 'Please choose a payment screenshot to upload.',
            'payment_proof.image' => 'Payment proof must be an image file.',
            'payment_proof.mimes' => 'Payment proof must be a JPG, PNG, WEBP, or GIF image.',
            'payment_proof.max' => 'Payment proof must be smaller than '.(int) config('coffee.payments.proof_max_kilobytes', 5120).' KB.',
        ];
    }
}
