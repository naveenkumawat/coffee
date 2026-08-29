<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerForgotPasswordRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class CustomerPasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.forgot-password');
    }

    public function store(CustomerForgotPasswordRequest $request): RedirectResponse
    {
        $user = User::query()
            ->where('email', strtolower(trim((string) $request->validated('email'))))
            ->where('role', UserRole::Customer->value)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'We could not find an active customer account with that email address.',
            ]);
        }

        $status = Password::broker('users')->sendResetLink([
            'email' => $user->email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return back()->with('status', __($status));
    }
}
