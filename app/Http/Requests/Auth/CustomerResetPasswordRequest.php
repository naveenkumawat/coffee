<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\AbstractRequest;

class CustomerResetPasswordRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
