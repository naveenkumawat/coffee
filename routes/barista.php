<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Barista\DashboardController;
use App\Http\Controllers\Barista\InventoryController;
use App\Http\Controllers\Barista\InventoryRefillRequestController;
use App\Http\Controllers\Barista\OrderController;
use App\Http\Controllers\Barista\ProductController;
use App\Http\Controllers\Barista\RecipeController;
use App\Http\Controllers\Internal\StaffNotificationController;
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
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
    Route::post('/orders/{order}/cash/receive', [OrderController::class, 'markCashReceived'])->name('orders.cash.receive');
    Route::get('/orders/{order}/invoice/pdf', [OrderController::class, 'downloadInvoice'])->name('orders.invoice.pdf');
    Route::get('/orders/{order}/invoice/print', [OrderController::class, 'printInvoice'])->name('orders.invoice.print');
    Route::get('/orders/{order}/invoice/receipt', [OrderController::class, 'printReceipt'])->name('orders.invoice.receipt');
    Route::post('/notifications/read-all', [StaffNotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [StaffNotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/refill-requests', [InventoryRefillRequestController::class, 'index'])->name('refill-requests.index');
    Route::get('/refill-requests/create', [InventoryRefillRequestController::class, 'create'])->name('refill-requests.create');
    Route::post('/refill-requests', [InventoryRefillRequestController::class, 'store'])->name('refill-requests.store');
    Route::get('/refill-requests/{inventoryRefillRequest}', [InventoryRefillRequestController::class, 'show'])->name('refill-requests.show');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->defaults('panel', 'barista')
        ->name('logout');
});
