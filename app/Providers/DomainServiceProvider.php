<?php

namespace App\Providers;

use App\Services\Auth\RoleService;
use App\Services\Auth\RoleServiceInterface;
use App\Services\Ingredient\IngredientBrandService;
use App\Services\Ingredient\IngredientBrandServiceInterface;
use App\Services\Ingredient\IngredientCategoryService;
use App\Services\Ingredient\IngredientCategoryServiceInterface;
use App\Services\Ingredient\IngredientService;
use App\Services\Ingredient\IngredientServiceInterface;
use App\Services\Inventory\InventoryRefillRequestService;
use App\Services\Inventory\InventoryRefillRequestServiceInterface;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\InventoryServiceInterface;
use App\Services\Menu\MenuCatalogService;
use App\Services\Menu\MenuCatalogServiceInterface;
use App\Services\Menu\MenuCategoryService;
use App\Services\Menu\MenuCategoryServiceInterface;
use App\Services\Menu\MenuItemService;
use App\Services\Menu\MenuItemServiceInterface;
use App\Services\User\UserService;
use App\Services\User\UserServiceInterface;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IngredientCategoryServiceInterface::class, IngredientCategoryService::class);
        $this->app->bind(IngredientBrandServiceInterface::class, IngredientBrandService::class);
        $this->app->bind(IngredientServiceInterface::class, IngredientService::class);
        $this->app->bind(InventoryRefillRequestServiceInterface::class, InventoryRefillRequestService::class);
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
        $this->app->bind(RoleServiceInterface::class, RoleService::class);
        $this->app->bind(MenuCatalogServiceInterface::class, MenuCatalogService::class);
        $this->app->bind(MenuCategoryServiceInterface::class, MenuCategoryService::class);
        $this->app->bind(MenuItemServiceInterface::class, MenuItemService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
    }
}
