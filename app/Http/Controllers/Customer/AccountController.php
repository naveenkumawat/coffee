<?php

namespace App\Http\Controllers\Customer;

use App\Events\Customer\CustomerPasswordChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerPasswordUpdateRequest;
use App\Http\Requests\Customer\CustomerProfileUpdateRequest;
use App\Parsers\User\UserParserInterface;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(
        protected UserParserInterface $parser,
        protected UserRepositoryInterface $users,
    ) {}

    public function show(): View
    {
        return view('customer.account.show', [
            'customer' => request()->user()->loadMissing([]),
        ]);
    }

    public function updateProfile(CustomerProfileUpdateRequest $request): RedirectResponse
    {
        $customer = $request->user();
        $data = $request->validated() + [
            'role' => $customer->role->value,
            'is_active' => (bool) $customer->is_active,
        ];

        DB::transaction(fn () => $this->users->update(
            $customer,
            $this->parser->getTransferFromArrayData($data)->toArray(),
        ));

        return redirect()->route('customer.account.show')->with('status', 'Profile updated successfully.');
    }

    public function updatePassword(CustomerPasswordUpdateRequest $request): RedirectResponse
    {
        $customer = $request->user();

        DB::transaction(function () use ($customer, $request): void {
            $this->users->update($customer, [
                'password' => (string) $request->validated('password'),
            ]);
        });

        CustomerPasswordChanged::dispatch($customer->fresh());

        return redirect()->route('customer.account.show')->with('status', 'Password updated successfully.');
    }
}
