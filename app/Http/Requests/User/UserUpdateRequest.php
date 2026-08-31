<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Http\Requests\AbstractRequest;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageUsers() ?? false;
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::in([
                UserRole::Owner->value,
                UserRole::Barista->value,
                UserRole::Customer->value,
            ])],
            'is_active' => ['nullable', 'boolean'],
            'cash_takeaway_allowed' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'cash_takeaway_allowed' => $this->boolean('cash_takeaway_allowed'),
        ]);
    }
}
