<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class CustomerProfileUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('customer') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->user()?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
