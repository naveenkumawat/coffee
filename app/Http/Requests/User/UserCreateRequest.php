<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserCreateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageUsers() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:120', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::in([
                UserRole::Owner->value,
                UserRole::Barista->value,
                UserRole::Customer->value,
            ])],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
