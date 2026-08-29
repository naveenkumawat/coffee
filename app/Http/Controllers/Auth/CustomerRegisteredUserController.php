<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Parsers\User\UserParserInterface;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerRegisteredUserController extends Controller
{
    public function __construct(
        protected UserParserInterface $parser,
        protected UserRepositoryInterface $users,
    ) {}

    public function create(): View
    {
        return view('customer.auth.register');
    }

    public function store(CustomerRegisterRequest $request): RedirectResponse
    {
        $data = $request->validated() + [
            'role' => UserRole::Customer->value,
            'is_active' => true,
        ];

        $user = DB::transaction(fn () => $this->users->create(
            $this->parser->getTransferFromArrayData($data)->toArray()
            + ['email_verified_at' => now(), 'last_login_at' => now()],
        ));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.account.show');
    }
}
