<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Barista\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth('admin')->user();

    if ($user?->canAccessInternalPanel('barista')) {
        return redirect()->route('barista.dashboard');
    }

    return redirect()->route('barista.login');
})->name('root');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->defaults('panel', 'barista')
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->defaults('panel', 'barista')
        ->name('login.store');
});

Route::middleware(['auth:admin', 'role:barista'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->defaults('panel', 'barista')
        ->name('logout');
});
