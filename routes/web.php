<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::prefix('administrator')->name('administrator.')->group(base_path('routes/administrator.php'));
Route::prefix('barista')->name('barista.')->group(base_path('routes/barista.php'));
