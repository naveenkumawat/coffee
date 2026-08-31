<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Events\Customer\CustomerPasswordChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerResetPasswordRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerNewPasswordController extends Controller
{
    public function create(string $token): View
    {
        return view('customer.auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
        ]);
    }

    public function store(CustomerResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                if (! $user->hasRole(UserRole::Customer)) {
                    throw ValidationException::withMessages([
                        'email' => 'Only customer accounts can reset passwords here.',
                    ]);
                }

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'last_login_at' => now(),
                ])->save();

                CustomerPasswordChanged::dispatch($user);

                Auth::guard('web')->login($user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('customer.account.show')->with('status', __($status));
    }
}
