<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class OrderIndexRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canViewOrders() ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(array_keys(OrderStatus::options()))],
            'customer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'assigned_barista_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ];
    }
}
