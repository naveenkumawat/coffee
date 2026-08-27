<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::prefix('admin')->name('admin.')->group(base_path('routes/auth.php'));
Route::prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
