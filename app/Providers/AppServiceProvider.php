<?php

namespace App\Providers;

use App\Services\Cart\CartServiceInterface;
use Illuminate\Pagination\Paginator;
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

        View::composer('layouts.app', function ($view): void {
            $customer = auth('web')->user();
            $customerCartCount = 0;

            if ($customer?->hasRole('customer')) {
                $customerCartCount = app(CartServiceInterface::class)->count($customer);
            }

            $view->with('customerCartCount', $customerCartCount);
        });
    }
}
