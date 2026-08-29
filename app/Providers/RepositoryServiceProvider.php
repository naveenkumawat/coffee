<?php

namespace App\Providers;

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
        $this->app->bind(MenuCategoryRepositoryInterface::class, MenuCategoryRepository::class);
        $this->app->bind(MenuItemRepositoryInterface::class, MenuItemRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }
}
