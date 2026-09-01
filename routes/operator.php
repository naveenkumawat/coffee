<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Internal\StaffNotificationController;
use App\Http\Controllers\Operator\DashboardController;
use App\Http\Controllers\Operator\DiningSessionController;
use App\Http\Controllers\Operator\InventoryController;
use App\Http\Controllers\Operator\InventoryRefillRequestController;
use App\Http\Controllers\Operator\OrderController;
use App\Http\Controllers\Operator\PreparationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth('admin')->user();

    if ($user?->canAccessInternalPanel('operator')) {
        return redirect()->route('operator.dashboard');
    }

    return redirect()->route('operator.login');
})->name('root');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->defaults('panel', 'operator')
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->defaults('panel', 'operator')
        ->name('login.store');
});

Route::middleware(['auth:admin', 'role:operator'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
    Route::post('/orders/{order}/cash/receive', [OrderController::class, 'markCashReceived'])->name('orders.cash.receive');
    Route::get('/orders/{order}/invoice/pdf', [OrderController::class, 'downloadInvoice'])->name('orders.invoice.pdf');
    Route::get('/orders/{order}/invoice/print', [OrderController::class, 'printInvoice'])->name('orders.invoice.print');
    Route::get('/orders/{order}/invoice/receipt', [OrderController::class, 'printReceipt'])->name('orders.invoice.receipt');
    Route::get('/dining-sessions', [DiningSessionController::class, 'index'])->name('dining-sessions.index');
    Route::get('/dining-sessions/{diningSession}', [DiningSessionController::class, 'show'])->name('dining-sessions.show');
    Route::post('/dining-sessions/{diningSession}/close', [DiningSessionController::class, 'close'])->name('dining-sessions.close');
    Route::post('/dining-sessions/{diningSession}/reopen', [DiningSessionController::class, 'reopen'])->name('dining-sessions.reopen');
    Route::get('/dining-sessions/{diningSession}/invoice', [DiningSessionController::class, 'invoice'])->name('dining-sessions.invoice');
    Route::get('/preparations', [PreparationController::class, 'index'])->name('preparations.index');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/refill-requests', [InventoryRefillRequestController::class, 'index'])->name('refill-requests.index');
    Route::get('/refill-requests/create', [InventoryRefillRequestController::class, 'create'])->name('refill-requests.create');
    Route::post('/refill-requests', [InventoryRefillRequestController::class, 'store'])->name('refill-requests.store');
    Route::get('/refill-requests/{inventoryRefillRequest}', [InventoryRefillRequestController::class, 'show'])->name('refill-requests.show');
    Route::post('/notifications/read-all', [StaffNotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [StaffNotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->defaults('panel', 'operator')
        ->name('logout');
});
