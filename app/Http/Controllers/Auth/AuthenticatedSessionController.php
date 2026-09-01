<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        $panel = $this->panelFromRequest($request);

        return view("{$panel}.auth.login", [
            'panel' => $panel,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $panel = $this->panelFromRequest($request);

        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user('admin');

        if (! $user?->canAccessInternalPanel($panel)) {
            Auth::guard('admin')->logout();
            $request->session()->regenerate();

            throw ValidationException::withMessages([
                'email' => "This account cannot access the {$panel} panel.",
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended(route("{$panel}.dashboard"));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $panel = $this->panelFromRequest($request);

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route("{$panel}.login");
    }

    protected function panelFromRequest(Request $request): string
    {
        $panel = (string) str($request->route()?->getName() ?? 'administrator.login')->before('.');

        return in_array($panel, ['administrator', 'barista', 'waiter'], true)
            ? $panel
            : 'administrator';
    }
}
