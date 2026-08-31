<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\ProductRating;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Observers\PublicCatalogCacheObserver;
use App\Services\Cart\CartServiceInterface;
use App\Services\Product\ProductCatalogServiceInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        $this->configureRateLimiting();
        $this->configurePublicCatalogCacheInvalidation();

        Event::listen(DatabaseRefreshed::class, function (): void {
            app(ProductCatalogServiceInterface::class)->flushPublicCache();
        });

        View::composer('layouts.app', function ($view): void {
            $customer = auth('web')->user();
            $customerCartCount = 0;

            if ($customer?->hasRole('customer')) {
                $customerCartCount = app(CartServiceInterface::class)->count($customer);
            }

            $view->with('customerCartCount', $customerCartCount);
        });

        View::composer('internal.includes.partials.header', function ($view): void {
            $user = auth('admin')->user();
            $staffNotifications = collect();
            $staffUnreadCount = 0;

            if ($user) {
                $staffNotifications = $user->notifications()
                    ->latest()
                    ->limit(12)
                    ->get();
                $staffUnreadCount = $user->unreadNotifications()->count();
            }

            $view->with([
                'staffNotifications' => $staffNotifications,
                'staffUnreadCount' => $staffUnreadCount,
            ]);
        });
    }

    protected function configurePublicCatalogCacheInvalidation(): void
    {
        $observer = PublicCatalogCacheObserver::class;

        Product::observe($observer);
        ProductVariant::observe($observer);
        ProductCategory::observe($observer);
        ProductFlavour::observe($observer);
        ProductTag::observe($observer);
        ProductRating::observe($observer);
        Recipe::observe($observer);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('customer-auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('customer-password', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('payment-proof', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('product-rating', function (Request $request) {
            return Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
