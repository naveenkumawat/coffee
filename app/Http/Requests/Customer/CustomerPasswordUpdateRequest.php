<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\AbstractRequest;

class CustomerPasswordUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('customer') ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
