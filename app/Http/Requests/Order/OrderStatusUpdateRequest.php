<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class OrderStatusUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canViewOrders() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(OrderStatus::options()))],
            'notes' => ['nullable', 'string'],
        ];
    }
}
