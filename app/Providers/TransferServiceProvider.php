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
use App\Transfers\Inventory\InventoryHistoryFilterTransfer;
use App\Transfers\Inventory\InventoryHistoryFilterTransferInterface;
use App\Transfers\Inventory\InventoryOverviewFilterTransfer;
use App\Transfers\Inventory\InventoryOverviewFilterTransferInterface;
use App\Transfers\Inventory\InventoryRefillRequestFilterTransfer;
use App\Transfers\Inventory\InventoryRefillRequestFilterTransferInterface;
use App\Transfers\Inventory\InventoryRefillRequestTransfer;
use App\Transfers\Inventory\InventoryRefillRequestTransferInterface;
use App\Transfers\Inventory\InventoryTransactionTransfer;
use App\Transfers\Inventory\InventoryTransactionTransferInterface;
use App\Transfers\Menu\MenuCategoryTransfer;
use App\Transfers\Menu\MenuCategoryTransferInterface;
use App\Transfers\Menu\MenuItemTransfer;
use App\Transfers\Menu\MenuItemTransferInterface;
use App\Transfers\Product\ProductCategoryFilterTransfer;
use App\Transfers\Product\ProductCategoryFilterTransferInterface;
use App\Transfers\Product\ProductCategoryTransfer;
use App\Transfers\Product\ProductCategoryTransferInterface;
use App\Transfers\Product\ProductFilterTransfer;
use App\Transfers\Product\ProductFilterTransferInterface;
use App\Transfers\Product\ProductFlavourFilterTransfer;
use App\Transfers\Product\ProductFlavourFilterTransferInterface;
use App\Transfers\Product\ProductFlavourTransfer;
use App\Transfers\Product\ProductFlavourTransferInterface;
use App\Transfers\Product\ProductTransfer;
use App\Transfers\Product\ProductTransferInterface;
use App\Transfers\Recipe\RecipeFilterTransfer;
use App\Transfers\Recipe\RecipeFilterTransferInterface;
use App\Transfers\Recipe\RecipeTransfer;
use App\Transfers\Recipe\RecipeTransferInterface;
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
        $this->app->bind(InventoryRefillRequestTransferInterface::class, InventoryRefillRequestTransfer::class);
        $this->app->bind(InventoryRefillRequestFilterTransferInterface::class, InventoryRefillRequestFilterTransfer::class);
        $this->app->bind(InventoryTransactionTransferInterface::class, InventoryTransactionTransfer::class);
        $this->app->bind(InventoryOverviewFilterTransferInterface::class, InventoryOverviewFilterTransfer::class);
        $this->app->bind(InventoryHistoryFilterTransferInterface::class, InventoryHistoryFilterTransfer::class);
        $this->app->bind(MenuCategoryTransferInterface::class, MenuCategoryTransfer::class);
        $this->app->bind(MenuItemTransferInterface::class, MenuItemTransfer::class);
        $this->app->bind(ProductCategoryTransferInterface::class, ProductCategoryTransfer::class);
        $this->app->bind(ProductCategoryFilterTransferInterface::class, ProductCategoryFilterTransfer::class);
        $this->app->bind(ProductFlavourTransferInterface::class, ProductFlavourTransfer::class);
        $this->app->bind(ProductFlavourFilterTransferInterface::class, ProductFlavourFilterTransfer::class);
        $this->app->bind(ProductTransferInterface::class, ProductTransfer::class);
        $this->app->bind(ProductFilterTransferInterface::class, ProductFilterTransfer::class);
        $this->app->bind(RecipeTransferInterface::class, RecipeTransfer::class);
        $this->app->bind(RecipeFilterTransferInterface::class, RecipeFilterTransfer::class);
        $this->app->bind(UserTransferInterface::class, UserTransfer::class);
        $this->app->bind(UserFilterTransferInterface::class, UserFilterTransfer::class);
    }
}
