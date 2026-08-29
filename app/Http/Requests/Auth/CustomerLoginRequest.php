<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Http\Requests\AbstractRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomerLoginRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $credentials = $this->safe()->only(['email', 'password']);
        $credentials['is_active'] = true;
        $credentials['role'] = UserRole::Customer->value;

        if (! Auth::guard('web')->attempt($credentials, (bool) $this->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our customer accounts.',
            ]);
        }
    }
}
