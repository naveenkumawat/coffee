<?php

namespace App\Providers;

use App\Services\Auth\RoleService;
use App\Services\Menu\MenuCatalogService;
use App\Services\Menu\MenuCategoryService;
use App\Services\Menu\MenuItemService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleService::class);
        $this->app->singleton(MenuCatalogService::class);
        $this->app->singleton(MenuCategoryService::class);
        $this->app->singleton(MenuItemService::class);
    }
}
