<?php

use App\Http\Controllers\Auth\CustomerAuthenticatedSessionController;
use App\Http\Controllers\Auth\CustomerNewPasswordController;
use App\Http\Controllers\Auth\CustomerPasswordResetLinkController;
use App\Http\Controllers\Auth\CustomerRegisteredUserController;
use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:web')->group(function (): void {
    Route::get('/login', [CustomerAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [CustomerAuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [CustomerRegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [CustomerRegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [CustomerPasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [CustomerPasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [CustomerNewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [CustomerNewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth:web', 'role:customer'])->group(function (): void {
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::put('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::get('/account/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/account/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/logout', [CustomerAuthenticatedSessionController::class, 'destroy'])->name('logout');
});
