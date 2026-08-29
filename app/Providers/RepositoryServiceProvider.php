<?php

namespace App\Providers;

use App\Repositories\Ingredient\IngredientBrandRepository;
use App\Repositories\Ingredient\IngredientBrandRepositoryInterface;
use App\Repositories\Ingredient\IngredientCategoryRepository;
use App\Repositories\Ingredient\IngredientCategoryRepositoryInterface;
use App\Repositories\Ingredient\IngredientRepository;
use App\Repositories\Ingredient\IngredientRepositoryInterface;
use App\Repositories\Inventory\InventoryRefillRequestRepository;
use App\Repositories\Inventory\InventoryRefillRequestRepositoryInterface;
use App\Repositories\Inventory\InventoryRepository;
use App\Repositories\Inventory\InventoryRepositoryInterface;
use App\Repositories\Menu\MenuCategoryRepository;
use App\Repositories\Menu\MenuCategoryRepositoryInterface;
use App\Repositories\Menu\MenuItemRepository;
use App\Repositories\Menu\MenuItemRepositoryInterface;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Repositories\Product\ProductCategoryRepository;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Product\ProductFlavourRepository;
use App\Repositories\Product\ProductFlavourRepositoryInterface;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\Recipe\RecipeRepository;
use App\Repositories\Recipe\RecipeRepositoryInterface;
use App\Repositories\User\UserRepository;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IngredientCategoryRepositoryInterface::class, IngredientCategoryRepository::class);
        $this->app->bind(IngredientBrandRepositoryInterface::class, IngredientBrandRepository::class);
        $this->app->bind(IngredientRepositoryInterface::class, IngredientRepository::class);
        $this->app->bind(InventoryRefillRequestRepositoryInterface::class, InventoryRefillRequestRepository::class);
        $this->app->bind(InventoryRepositoryInterface::class, InventoryRepository::class);
        $this->app->bind(MenuCategoryRepositoryInterface::class, MenuCategoryRepository::class);
        $this->app->bind(MenuItemRepositoryInterface::class, MenuItemRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(ProductCategoryRepositoryInterface::class, ProductCategoryRepository::class);
        $this->app->bind(ProductFlavourRepositoryInterface::class, ProductFlavourRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(RecipeRepositoryInterface::class, RecipeRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }
}
