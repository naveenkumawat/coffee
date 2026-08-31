<?php

namespace App\Providers;

use App\Services\Cart\CartServiceInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
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

        View::composer('layouts.app', function ($view): void {
            $customer = auth('web')->user();
            $customerCartCount = 0;

            if ($customer?->hasRole('customer')) {
                $customerCartCount = app(CartServiceInterface::class)->count($customer);
            }

            $view->with('customerCartCount', $customerCartCount);
        });
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
