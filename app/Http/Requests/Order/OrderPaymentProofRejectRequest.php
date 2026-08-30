<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\AbstractRequest;
use App\Models\Order;

class OrderPaymentProofRejectRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        /** @var Order|null $order */
        $order = $this->route('order');

        return $this->user('admin')?->can('rejectPaymentProof', $order) ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
