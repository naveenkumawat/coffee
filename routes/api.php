<?php

use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\CafeAvailability\CafeAvailabilityController;
use App\Http\Controllers\Api\V1\CafeTable\CafeTableController;
use App\Http\Controllers\Api\V1\Catalog\CatalogController;
use App\Http\Controllers\Api\V1\Content\WebsiteContentController;
use App\Http\Controllers\Api\V1\Customer\CustomerAccountController;
use App\Http\Controllers\Api\V1\Customer\CustomerCartController;
use App\Http\Controllers\Api\V1\Customer\CustomerCheckoutController;
use App\Http\Controllers\Api\V1\Customer\CustomerDiningController;
use App\Http\Controllers\Api\V1\Customer\CustomerFavouriteController;
use App\Http\Controllers\Api\V1\Customer\CustomerOrderController;
use App\Http\Controllers\Api\V1\Customer\CustomerProductRatingController;
use App\Http\Controllers\Api\V1\Customer\CustomerReferralController;
use App\Http\Controllers\Api\V1\Customer\CustomerRewardController;
use App\Http\Controllers\Api\V1\Home\HomeController;
use App\Http\Controllers\Api\V1\Notification\OperationalNotificationController;
use App\Http\Controllers\Api\V1\Waiter\WaiterDiningController;
use App\Http\Middleware\AuthenticateNotificationRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/register', [CustomerAuthController::class, 'register'])
            ->middleware('throttle:customer-auth')
            ->name('register');
        Route::post('/login', [CustomerAuthController::class, 'login'])
            ->middleware('throttle:customer-auth')
            ->name('login');
        Route::post('/forgot-password', [CustomerAuthController::class, 'forgotPassword'])
            ->middleware('throttle:customer-password')
            ->name('password.forgot');
        Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])
            ->middleware('throttle:customer-password')
            ->name('password.reset');
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
    Route::get('/cafe-availability', [CafeAvailabilityController::class, 'show'])->name('cafe-availability.show');
    Route::get('/home', [HomeController::class, 'show'])->name('home.show');
    Route::get('/cafe-tables', [CafeTableController::class, 'index'])->name('cafe-tables.index');

    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::get('/me', [CustomerAuthController::class, 'me'])->name('me');
            Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
        });
    });

    Route::middleware([AuthenticateNotificationRequest::class])->prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [OperationalNotificationController::class, 'index'])->name('index');
        Route::get('/action-required', [OperationalNotificationController::class, 'actionRequired'])->name('action-required');
        Route::post('/{recipient}/delivered', [OperationalNotificationController::class, 'delivered'])->name('delivered');
        Route::post('/{recipient}/seen', [OperationalNotificationController::class, 'seen'])->name('seen');
        Route::post('/{recipient}/read', [OperationalNotificationController::class, 'read'])->name('read');
        Route::post('/{recipient}/acknowledge', [OperationalNotificationController::class, 'acknowledge'])->name('acknowledge');
    });

    Route::middleware(['auth:sanctum', 'role:waiter'])->prefix('waiter')->name('waiter.')->group(function (): void {
        Route::get('/tables', [WaiterDiningController::class, 'tables'])->name('tables.index');
        Route::post('/sessions', [WaiterDiningController::class, 'storeSession'])->name('sessions.store');
        Route::get('/sessions/{session}', [WaiterDiningController::class, 'showSession'])->name('sessions.show');
        Route::post('/sessions/{session}/drafts', [WaiterDiningController::class, 'storeDraft'])->name('sessions.drafts.store');
        Route::put('/sessions/{session}/drafts/{draft}', [WaiterDiningController::class, 'updateDraft'])->name('sessions.drafts.update');
        Route::delete('/sessions/{session}/drafts/{draft}', [WaiterDiningController::class, 'destroyDraft'])->name('sessions.drafts.destroy');
        Route::delete('/sessions/{session}/drafts', [WaiterDiningController::class, 'clearDrafts'])->name('sessions.drafts.clear');
        Route::post('/sessions/{session}/rounds', [WaiterDiningController::class, 'placeRound'])->name('sessions.rounds.store');
        Route::post('/sessions/{session}/request-bill', [WaiterDiningController::class, 'requestBill'])->name('sessions.request-bill');
        Route::post('/sessions/{session}/payment-method', [WaiterDiningController::class, 'setPaymentMethod'])->name('sessions.payment-method');
        Route::post('/sessions/{session}/cash', [WaiterDiningController::class, 'markCashReceived'])->name('sessions.cash.receive');
        Route::post('/sessions/{session}/close', [WaiterDiningController::class, 'close'])->name('sessions.close');
        Route::post('/sessions/{session}/reopen', [WaiterDiningController::class, 'reopen'])->name('sessions.reopen');
        Route::get('/sessions/{session}/invoice', [WaiterDiningController::class, 'invoice'])->name('sessions.invoice');
    });

    Route::middleware(['auth:sanctum', 'role:customer'])->group(function (): void {
        Route::prefix('customer')->name('customer.')->group(function (): void {
            Route::get('/me', [CustomerAccountController::class, 'show'])->name('me');
            Route::put('/profile', [CustomerAccountController::class, 'updateProfile'])->name('profile.update');
            Route::put('/password', [CustomerAccountController::class, 'updatePassword'])->name('password.update');
            Route::get('/referral', [CustomerReferralController::class, 'show'])->name('referral.show');
            Route::get('/rewards', [CustomerRewardController::class, 'index'])->name('rewards.index');
        });

        Route::prefix('cart')->name('cart.')->group(function (): void {
            Route::get('/', [CustomerCartController::class, 'show'])->name('show');
            Route::get('/count', [CustomerCartController::class, 'count'])->name('count');
            Route::post('/merge', [CustomerCartController::class, 'merge'])->name('merge');
            Route::post('/items', [CustomerCartController::class, 'store'])->name('items.store');
            Route::put('/items/{cartItem}', [CustomerCartController::class, 'update'])->name('items.update');
            Route::delete('/items/{cartItem}', [CustomerCartController::class, 'destroy'])->name('items.destroy');
            Route::post('/promo-code', [CustomerCartController::class, 'applyPromoCode'])->name('promo-code.apply');
            Route::delete('/promo-code', [CustomerCartController::class, 'clearPromoCode'])->name('promo-code.clear');
            Route::post('/referral-rewards/free-drink', [CustomerRewardController::class, 'addFreeDrinkToCart'])->name('referral-rewards.free-drink');
            Route::post('/referral-rewards/coupon', [CustomerRewardController::class, 'applyCoupon'])->name('referral-rewards.coupon.apply');
            Route::delete('/referral-rewards', [CustomerRewardController::class, 'clear'])->name('referral-rewards.clear');
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

        Route::prefix('products/{product}/rating')->name('products.rating.')->middleware('throttle:product-rating')->group(function (): void {
            Route::post('/', [CustomerProductRatingController::class, 'store'])->name('store');
            Route::put('/', [CustomerProductRatingController::class, 'update'])->name('update');
            Route::delete('/', [CustomerProductRatingController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('dining')->name('dining.')->group(function (): void {
            Route::get('/tables', [CustomerDiningController::class, 'tables'])->name('tables');
            Route::post('/sessions', [CustomerDiningController::class, 'storeSession'])->name('sessions.store');
            Route::get('/sessions/active', [CustomerDiningController::class, 'activeSession'])->name('sessions.active');
            Route::get('/sessions/{session}', [CustomerDiningController::class, 'showSession'])->name('sessions.show');
            Route::post('/sessions/{session}/drafts', [CustomerDiningController::class, 'storeDraft'])->name('sessions.drafts.store');
            Route::put('/sessions/{session}/drafts/{draft}', [CustomerDiningController::class, 'updateDraft'])->name('sessions.drafts.update');
            Route::delete('/sessions/{session}/drafts/{draft}', [CustomerDiningController::class, 'destroyDraft'])->name('sessions.drafts.destroy');
            Route::delete('/sessions/{session}/drafts', [CustomerDiningController::class, 'clearDrafts'])->name('sessions.drafts.clear');
            Route::post('/sessions/{session}/rounds', [CustomerDiningController::class, 'placeRound'])->name('sessions.rounds.store');
            Route::post('/sessions/{session}/request-bill', [CustomerDiningController::class, 'requestBill'])->name('sessions.request-bill');
            Route::post('/sessions/{session}/payment-method', [CustomerDiningController::class, 'setPaymentMethod'])->name('sessions.payment-method');
            Route::post('/sessions/{session}/payment-proof', [CustomerDiningController::class, 'uploadPaymentProof'])
                ->middleware('throttle:payment-proof')
                ->name('sessions.payment-proof');
            Route::get('/sessions/{session}/invoice', [CustomerDiningController::class, 'invoice'])->name('sessions.invoice');
        });

        Route::prefix('orders')->name('orders.')->group(function (): void {
            Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
            Route::get('/{order}', [CustomerOrderController::class, 'show'])->name('show');
            Route::post('/{order}/payment-proof', [CustomerOrderController::class, 'uploadPaymentProof'])
                ->middleware('throttle:payment-proof')
                ->name('payment-proof.upload');
            Route::get('/{order}/payment-proof', [CustomerOrderController::class, 'paymentProof'])->name('payment-proof.show');
            Route::get('/{order}/invoice', [CustomerOrderController::class, 'invoice'])->name('invoice.download');
        });
    });
});
