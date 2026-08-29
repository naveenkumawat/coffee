<?php

namespace App\Providers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Policies\MenuCategoryPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        MenuCategory::class => MenuCategoryPolicy::class,
        MenuItem::class => MenuItemPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        Gate::before(fn ($user, string $ability) => $user->hasRole('owner') ? true : null);
    }
}
