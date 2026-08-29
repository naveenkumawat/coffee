<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::name('customer.')->group(base_path('routes/customer.php'));

Route::prefix('administrator')->name('administrator.')->group(base_path('routes/administrator.php'));
Route::prefix('barista')->name('barista.')->group(base_path('routes/barista.php'));
