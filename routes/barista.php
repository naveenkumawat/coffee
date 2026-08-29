<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Barista\DashboardController;
use App\Http\Controllers\Barista\InventoryController;
use App\Http\Controllers\Barista\InventoryRefillRequestController;
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
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/refill-requests', [InventoryRefillRequestController::class, 'index'])->name('refill-requests.index');
    Route::get('/refill-requests/create', [InventoryRefillRequestController::class, 'create'])->name('refill-requests.create');
    Route::post('/refill-requests', [InventoryRefillRequestController::class, 'store'])->name('refill-requests.store');
    Route::get('/refill-requests/{inventoryRefillRequest}', [InventoryRefillRequestController::class, 'show'])->name('refill-requests.show');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->defaults('panel', 'barista')
        ->name('logout');
});
