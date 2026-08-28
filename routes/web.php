<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::redirect('/admin', '/administrator/login');
Route::redirect('/admin/login', '/administrator/login');
Route::redirect('/admin/dashboard', '/administrator/dashboard');

Route::prefix('administrator')->name('administrator.')->group(base_path('routes/administrator.php'));
Route::prefix('barista')->name('barista.')->group(base_path('routes/barista.php'));
