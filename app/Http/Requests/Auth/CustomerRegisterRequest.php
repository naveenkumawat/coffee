<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\AbstractRequest;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

class CustomerRegisterRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => PhoneNumber::normalize($this->input('phone')),
            ]);
        }

        if ($this->filled('email')) {
            $this->merge([
                'email' => mb_strtolower(trim((string) $this->input('email'))),
            ]);
        }

        if ($this->has('referral_code')) {
            $code = strtoupper(preg_replace('/\s+/', '', trim((string) $this->input('referral_code'))) ?? '');
            $this->merge([
                'referral_code' => $code === '' ? null : $code,
            ]);
        }
    }
}
