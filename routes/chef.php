<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Chef\DashboardController;
use App\Http\Controllers\Chef\PreparationController;
use App\Http\Controllers\Internal\DocumentationController;
use App\Http\Controllers\Internal\StaffNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth('admin')->user();

    if ($user?->canAccessInternalPanel('chef')) {
        return redirect()->route('chef.dashboard');
    }

    return redirect()->route('chef.login');
})->name('root');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->defaults('panel', 'chef')
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->defaults('panel', 'chef')
        ->name('login.store');
});

Route::middleware(['auth:admin', 'role:chef'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/documentation', [DocumentationController::class, 'index'])
        ->defaults('panel', 'chef')
        ->name('documentation.index');
    Route::get('/documentation/{module}', [DocumentationController::class, 'show'])
        ->defaults('panel', 'chef')
        ->name('documentation.show');
    Route::get('/preparations', [PreparationController::class, 'index'])->name('preparations.index');
    Route::post('/preparations/{orderPreparation}/accept', [PreparationController::class, 'accept'])
        ->name('preparations.accept');
    Route::post('/preparations/{orderPreparation}/preparing', [PreparationController::class, 'preparing'])
        ->name('preparations.preparing');
    Route::post('/preparations/{orderPreparation}/ready', [PreparationController::class, 'ready'])
        ->name('preparations.ready');
    Route::post('/notifications/read-all', [StaffNotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [StaffNotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->defaults('panel', 'chef')
        ->name('logout');
});
