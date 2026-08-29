<?php

namespace App\Providers;

use App\Services\Auth\RoleService;
use App\Services\Auth\RoleServiceInterface;
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
        $this->app->bind(RoleServiceInterface::class, RoleService::class);
        $this->app->bind(MenuCatalogServiceInterface::class, MenuCatalogService::class);
        $this->app->bind(MenuCategoryServiceInterface::class, MenuCategoryService::class);
        $this->app->bind(MenuItemServiceInterface::class, MenuItemService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
    }
}
