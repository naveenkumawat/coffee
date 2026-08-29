<?php

namespace App\Providers;

use App\Models\Ingredient;
use App\Models\IngredientBrand;
use App\Models\IngredientCategory;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Policies\IngredientBrandPolicy;
use App\Policies\IngredientCategoryPolicy;
use App\Policies\IngredientPolicy;
use App\Policies\InventoryRefillRequestPolicy;
use App\Policies\InventoryTransactionPolicy;
use App\Policies\MenuCategoryPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Ingredient::class => IngredientPolicy::class,
        IngredientBrand::class => IngredientBrandPolicy::class,
        IngredientCategory::class => IngredientCategoryPolicy::class,
        InventoryRefillRequest::class => InventoryRefillRequestPolicy::class,
        InventoryTransaction::class => InventoryTransactionPolicy::class,
        MenuCategory::class => MenuCategoryPolicy::class,
        MenuItem::class => MenuItemPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        Gate::before(fn ($user, string $ability) => $user->hasRole('owner') ? true : null);
    }
}
