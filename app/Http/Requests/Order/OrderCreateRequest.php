<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class OrderCreateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageOrders() ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'customer_notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'nullable',
                'integer',
                'required_with:items.*.quantity',
                Rule::exists('product_variants', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'required_with:items.*.product_variant_id'],
        ];
    }
}
