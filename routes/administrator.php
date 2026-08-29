<?php

use App\Http\Controllers\Administrator\DashboardController;
use App\Http\Controllers\Administrator\MenuCategoryController;
use App\Http\Controllers\Administrator\MenuItemController;
use App\Http\Controllers\Administrator\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth('admin')->user();

    if ($user?->canAccessInternalPanel('administrator')) {
        return redirect()->route('administrator.dashboard');
    }

    return redirect()->route('administrator.login');
})->name('root');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->defaults('panel', 'administrator')
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->defaults('panel', 'administrator')
        ->name('login.store');
});

Route::middleware(['auth:admin', 'role:owner,manager'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('menu/categories', MenuCategoryController::class)
        ->parameters(['categories' => 'menu_category'])
        ->names('menu.categories');

    Route::resource('menu/items', MenuItemController::class)
        ->parameters(['items' => 'menu_item'])
        ->names('menu.items');

    Route::resource('users', UserController::class);

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->defaults('panel', 'administrator')
        ->name('logout');
});
