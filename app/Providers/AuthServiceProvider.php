<?php

namespace App\Providers;

use App\Models\Ingredient;
use App\Models\IngredientBrand;
use App\Models\IngredientCategory;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\Recipe;
use App\Models\User;
use App\Policies\IngredientBrandPolicy;
use App\Policies\IngredientCategoryPolicy;
use App\Policies\IngredientPolicy;
use App\Policies\InventoryRefillRequestPolicy;
use App\Policies\InventoryTransactionPolicy;
use App\Policies\MenuCategoryPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductFlavourPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RecipePolicy;
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
        Order::class => OrderPolicy::class,
        Product::class => ProductPolicy::class,
        ProductCategory::class => ProductCategoryPolicy::class,
        ProductFlavour::class => ProductFlavourPolicy::class,
        Recipe::class => RecipePolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        Gate::before(fn ($user, string $ability) => $user->hasRole('owner') ? true : null);
    }
}
