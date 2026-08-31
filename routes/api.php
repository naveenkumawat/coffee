<?php

use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\Catalog\CatalogController;
use App\Http\Controllers\Api\V1\Content\WebsiteContentController;
use App\Http\Controllers\Api\V1\Customer\CustomerAccountController;
use App\Http\Controllers\Api\V1\Customer\CustomerCartController;
use App\Http\Controllers\Api\V1\Customer\CustomerCheckoutController;
use App\Http\Controllers\Api\V1\Customer\CustomerFavouriteController;
use App\Http\Controllers\Api\V1\Customer\CustomerOrderController;
use App\Http\Controllers\Api\V1\Customer\CustomerProductRatingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/register', [CustomerAuthController::class, 'register'])->name('register');
        Route::post('/login', [CustomerAuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [CustomerAuthController::class, 'forgotPassword'])->name('password.forgot');
        Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])->name('password.reset');
    });

    Route::prefix('catalog')->name('catalog.')->group(function (): void {
        Route::get('/categories', [CatalogController::class, 'categories'])->name('categories.index');
        Route::get('/flavours', [CatalogController::class, 'flavours'])->name('flavours.index');
        Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
        Route::get('/products/featured', [CatalogController::class, 'featured'])->name('products.featured');
        Route::get('/products/{product}', [CatalogController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/ratings', [CustomerProductRatingController::class, 'index'])->name('products.ratings.index');
        Route::get('/variants', [CatalogController::class, 'variants'])->name('variants.index');
    });

    Route::get('/content', [WebsiteContentController::class, 'show'])->name('content.show');

    Route::middleware(['auth:sanctum', 'role:customer'])->group(function (): void {
        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::get('/me', [CustomerAuthController::class, 'me'])->name('me');
            Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
        });

        Route::prefix('customer')->name('customer.')->group(function (): void {
            Route::get('/me', [CustomerAccountController::class, 'show'])->name('me');
            Route::put('/profile', [CustomerAccountController::class, 'updateProfile'])->name('profile.update');
            Route::put('/password', [CustomerAccountController::class, 'updatePassword'])->name('password.update');
        });

        Route::prefix('cart')->name('cart.')->group(function (): void {
            Route::get('/', [CustomerCartController::class, 'show'])->name('show');
            Route::get('/count', [CustomerCartController::class, 'count'])->name('count');
            Route::post('/merge', [CustomerCartController::class, 'merge'])->name('merge');
            Route::post('/items', [CustomerCartController::class, 'store'])->name('items.store');
            Route::put('/items/{cartItem}', [CustomerCartController::class, 'update'])->name('items.update');
            Route::delete('/items/{cartItem}', [CustomerCartController::class, 'destroy'])->name('items.destroy');
            Route::delete('/', [CustomerCartController::class, 'clear'])->name('clear');
        });

        Route::prefix('checkout')->name('checkout.')->group(function (): void {
            Route::get('/summary', [CustomerCheckoutController::class, 'summary'])->name('summary');
            Route::post('/', [CustomerCheckoutController::class, 'store'])->name('store');
        });

        Route::prefix('favourites')->name('favourites.')->group(function (): void {
            Route::get('/', [CustomerFavouriteController::class, 'index'])->name('index');
            Route::get('/ids', [CustomerFavouriteController::class, 'ids'])->name('ids');
            Route::post('/', [CustomerFavouriteController::class, 'store'])->name('store');
            Route::delete('/{product}', [CustomerFavouriteController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('products/{product}/rating')->name('products.rating.')->group(function (): void {
            Route::post('/', [CustomerProductRatingController::class, 'store'])->name('store');
            Route::put('/', [CustomerProductRatingController::class, 'update'])->name('update');
            Route::delete('/', [CustomerProductRatingController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('orders')->name('orders.')->group(function (): void {
            Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
            Route::get('/{order}', [CustomerOrderController::class, 'show'])->name('show');
            Route::post('/{order}/payment-proof', [CustomerOrderController::class, 'uploadPaymentProof'])->name('payment-proof.upload');
            Route::get('/{order}/payment-proof', [CustomerOrderController::class, 'paymentProof'])->name('payment-proof.show');
        });
    });
});
