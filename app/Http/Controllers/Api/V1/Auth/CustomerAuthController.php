<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Events\Customer\CustomerPasswordChanged;
use App\Events\Customer\CustomerRegistered;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerForgotPasswordRequest;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Http\Requests\Auth\CustomerResetPasswordRequest;
use App\Http\Requests\Auth\SpaLoginRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\User;
use App\Parsers\User\UserParserInterface;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Referral\ReferralServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected UserParserInterface $parser,
        protected UserRepositoryInterface $users,
        protected ReferralServiceInterface $referrals,
    ) {}

    public function register(CustomerRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $referralCode = $validated['referral_code'] ?? null;
        unset($validated['referral_code']);

        $data = $validated + [
            'role' => UserRole::Customer->value,
            'is_active' => true,
        ];

        $user = DB::transaction(function () use ($data, $referralCode) {
            $user = $this->users->create(
                $this->parser->getTransferFromArrayData($data)->toArray()
                + ['email_verified_at' => now(), 'last_login_at' => now()],
            );

            $this->referrals->ensureCustomerReferralCode($user);
            $this->referrals->attachReferralOnRegistration($user, $referralCode);

            return $user->fresh();
        });

        CustomerRegistered::dispatch($user);

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $this->respondWithResource(
            new CustomerResource($user),
            'Customer account created successfully.',
            201,
        );
    }

    public function login(SpaLoginRequest $request): JsonResponse
    {
        $request->authenticate();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        /** @var User $user */
        $user = $request->user('web');
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $message = $user->hasRole(UserRole::Waiter)
            ? 'Waiter login successful.'
            : 'Customer login successful.';

        return $this->respondWithResource(
            new CustomerResource($user),
            $message,
            200,
        );
    }

    public function me(Request $request): JsonResponse
    {
        return $this->respondWithResource(
            new CustomerResource($request->user()),
            'Authenticated user retrieved.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $currentAccessToken = $request->user()?->currentAccessToken();

        if ($currentAccessToken && method_exists($currentAccessToken, 'delete')) {
            $currentAccessToken->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->respondWithData(
            null,
            $request->user()?->hasRole(UserRole::Waiter) ? 'Logout successful.' : 'Customer logout successful.',
        );
    }

    public function forgotPassword(CustomerForgotPasswordRequest $request): JsonResponse
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

        return $this->respondWithData(null, __($status));
    }

    public function resetPassword(CustomerResetPasswordRequest $request): JsonResponse
    {
        $resolvedUser = null;

        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resolvedUser): void {
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
                $resolvedUser = $user;
            }
        );

        if ($status !== Password::PASSWORD_RESET || ! $resolvedUser instanceof User) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $this->respondWithResource(
            new CustomerResource($resolvedUser),
            __($status),
            200,
        );
    }
}
