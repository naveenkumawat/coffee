<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::name('customer.')->group(base_path('routes/customer.php'));

Route::prefix('administrator')->name('administrator.')->group(base_path('routes/administrator.php'));
Route::prefix('operator')->name('operator.')->group(base_path('routes/operator.php'));
Route::prefix('barista')->name('barista.')->group(base_path('routes/barista.php'));
Route::prefix('chef')->name('chef.')->group(base_path('routes/chef.php'));
Route::prefix('waiter')->name('waiter.')->group(base_path('routes/waiter.php'));
