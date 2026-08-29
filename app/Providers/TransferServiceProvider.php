<?php

namespace App\Providers;

use App\Transfers\Ingredient\IngredientBrandFilterTransfer;
use App\Transfers\Ingredient\IngredientBrandFilterTransferInterface;
use App\Transfers\Ingredient\IngredientBrandTransfer;
use App\Transfers\Ingredient\IngredientBrandTransferInterface;
use App\Transfers\Ingredient\IngredientCategoryTransfer;
use App\Transfers\Ingredient\IngredientCategoryTransferInterface;
use App\Transfers\Ingredient\IngredientFilterTransfer;
use App\Transfers\Ingredient\IngredientFilterTransferInterface;
use App\Transfers\Ingredient\IngredientTransfer;
use App\Transfers\Ingredient\IngredientTransferInterface;
use App\Transfers\Menu\MenuCategoryTransfer;
use App\Transfers\Menu\MenuCategoryTransferInterface;
use App\Transfers\Menu\MenuItemTransfer;
use App\Transfers\Menu\MenuItemTransferInterface;
use App\Transfers\User\UserFilterTransfer;
use App\Transfers\User\UserFilterTransferInterface;
use App\Transfers\User\UserTransfer;
use App\Transfers\User\UserTransferInterface;
use Illuminate\Support\ServiceProvider;

class TransferServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IngredientCategoryTransferInterface::class, IngredientCategoryTransfer::class);
        $this->app->bind(IngredientBrandTransferInterface::class, IngredientBrandTransfer::class);
        $this->app->bind(IngredientBrandFilterTransferInterface::class, IngredientBrandFilterTransfer::class);
        $this->app->bind(IngredientTransferInterface::class, IngredientTransfer::class);
        $this->app->bind(IngredientFilterTransferInterface::class, IngredientFilterTransfer::class);
        $this->app->bind(MenuCategoryTransferInterface::class, MenuCategoryTransfer::class);
        $this->app->bind(MenuItemTransferInterface::class, MenuItemTransfer::class);
        $this->app->bind(UserTransferInterface::class, UserTransfer::class);
        $this->app->bind(UserFilterTransferInterface::class, UserFilterTransfer::class);
    }
}
