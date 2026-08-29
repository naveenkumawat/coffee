<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\AbstractRequest;

class CustomerForgotPasswordRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
