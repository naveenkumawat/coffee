<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Internal\StaffNotificationController;
use App\Http\Controllers\Waiter\DashboardController;
use App\Http\Controllers\Waiter\DiningSessionController;
use App\Http\Controllers\Waiter\TableController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth('admin')->user();

    if ($user?->canAccessInternalPanel('waiter')) {
        return redirect()->route('waiter.dashboard');
    }

    return redirect()->route('waiter.login');
})->name('root');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->defaults('panel', 'waiter')
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->defaults('panel', 'waiter')
        ->name('login.store');
});

Route::middleware(['auth:admin', 'role:waiter'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/tables', [TableController::class, 'index'])->name('tables.index');
    Route::get('/sessions', [DiningSessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/{session}', [DiningSessionController::class, 'show'])->name('sessions.show');
    Route::post('/sessions', [DiningSessionController::class, 'store'])->name('sessions.store');
    Route::post('/sessions/{session}/rounds', [DiningSessionController::class, 'placeRound'])->name('sessions.rounds.store');
    Route::post('/sessions/{session}/rounds/{order}/served', [DiningSessionController::class, 'markRoundServed'])->name('sessions.rounds.served');
    Route::post('/sessions/{session}/rounds/{order}/cancel', [DiningSessionController::class, 'cancelRound'])->name('sessions.rounds.cancel');
    Route::post('/sessions/{session}/request-bill', [DiningSessionController::class, 'requestBill'])->name('sessions.request-bill');
    Route::post('/sessions/{session}/payment-method', [DiningSessionController::class, 'changePaymentMethod'])->name('sessions.payment-method');
    Route::post('/sessions/{session}/cash', [DiningSessionController::class, 'markCashReceived'])->name('sessions.cash.receive');
    Route::post('/sessions/{session}/close', [DiningSessionController::class, 'close'])->name('sessions.close');
    Route::post('/sessions/{session}/reopen', [DiningSessionController::class, 'reopen'])->name('sessions.reopen');
    Route::get('/sessions/{session}/invoice', [DiningSessionController::class, 'invoice'])->name('sessions.invoice');
    Route::post('/notifications/read-all', [StaffNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [StaffNotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->defaults('panel', 'waiter')
        ->name('logout');
});
