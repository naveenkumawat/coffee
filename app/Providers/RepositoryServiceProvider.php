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
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }
}
