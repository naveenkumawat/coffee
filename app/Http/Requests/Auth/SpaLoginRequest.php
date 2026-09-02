<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Http\Requests\AbstractRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SpaLoginRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('login') && $this->filled('email')) {
            $this->merge([
                'login' => $this->input('email'),
            ]);
        }
    }

    public function authenticate(): void
    {
        $login = trim((string) $this->input('login'));
        $password = (string) $this->input('password');

        $user = $this->resolveSpaUser($login);

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            $message = 'The provided credentials do not match our records.';

            throw ValidationException::withMessages([
                'login' => $message,
                'email' => $message,
            ]);
        }

        Auth::guard('web')->login($user, (bool) $this->boolean('remember'));
    }

    /**
     * PWA may authenticate customers and waiters only.
     * Administrator / Operator / Barista / Chef must use their Blade panels.
     */
    protected function resolveSpaUser(string $login): ?User
    {
        $query = User::query()
            ->whereIn('role', [UserRole::Customer->value, UserRole::Waiter->value])
            ->where('is_active', true);

        if (PhoneNumber::looksLikeEmail($login)) {
            return $query->where('email', mb_strtolower($login))->first();
        }

        if (PhoneNumber::looksLikePhone($login)) {
            $phone = PhoneNumber::normalize($login);

            if ($phone === null) {
                return null;
            }

            return $query->where('phone', $phone)->first();
        }

        return null;
    }
}
