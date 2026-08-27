<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MenuCategoryController;
use App\Http\Controllers\Admin\MenuItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:admin', 'role:owner,manager,barista,cashier'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('menu/categories', MenuCategoryController::class)
        ->parameters(['categories' => 'menu_category'])
        ->names('menu.categories');

    Route::resource('menu/items', MenuItemController::class)
        ->parameters(['items' => 'menu_item'])
        ->names('menu.items');
});
