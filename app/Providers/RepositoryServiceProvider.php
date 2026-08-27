<?php

namespace App\Providers;

use App\Repositories\Menu\MenuCategoryRepository;
use App\Repositories\Menu\MenuItemRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuCategoryRepository::class);
        $this->app->singleton(MenuItemRepository::class);
    }
}
