<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Events\Customer\CustomerRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Parsers\User\UserParserInterface;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Referral\ReferralServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerRegisteredUserController extends Controller
{
    public function __construct(
        protected UserParserInterface $parser,
        protected UserRepositoryInterface $users,
        protected ReferralServiceInterface $referrals,
    ) {}

    public function create(): View
    {
        return view('customer.auth.register', [
            'referralCode' => request()->query('ref'),
        ]);
    }

    public function store(CustomerRegisterRequest $request): RedirectResponse
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
        $request->session()->regenerate();

        return redirect()->route('customer.account.show');
    }
}
