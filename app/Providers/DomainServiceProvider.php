<?php

namespace App\Providers;

use App\Services\Auth\RoleService;
use App\Services\Auth\RoleServiceInterface;
use App\Services\Cart\CartService;
use App\Services\Cart\CartServiceInterface;
use App\Services\Checkout\CheckoutService;
use App\Services\Checkout\CheckoutServiceInterface;
use App\Services\Favourite\FavouriteService;
use App\Services\Favourite\FavouriteServiceInterface;
use App\Services\Home\HomeSectionService;
use App\Services\Home\HomeSectionServiceInterface;
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
use App\Services\Order\OrderService;
use App\Services\Order\OrderServiceInterface;
use App\Services\Product\ProductCatalogService;
use App\Services\Product\ProductCatalogServiceInterface;
use App\Services\Product\ProductCategoryService;
use App\Services\Product\ProductCategoryServiceInterface;
use App\Services\Product\ProductFlavourService;
use App\Services\Product\ProductFlavourServiceInterface;
use App\Services\Product\ProductReadinessService;
use App\Services\Product\ProductReadinessServiceInterface;
use App\Services\Product\ProductService;
use App\Services\Product\ProductServiceInterface;
use App\Services\Product\ProductTagService;
use App\Services\Product\ProductTagServiceInterface;
use App\Services\Rating\ProductRatingService;
use App\Services\Rating\ProductRatingServiceInterface;
use App\Services\Recipe\RecipeCostingService;
use App\Services\Recipe\RecipeCostingServiceInterface;
use App\Services\Recipe\RecipeService;
use App\Services\Recipe\RecipeServiceInterface;
use App\Services\Social\SocialLinkService;
use App\Services\Social\SocialLinkServiceInterface;
use App\Services\User\UserService;
use App\Services\User\UserServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingService;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CartServiceInterface::class, CartService::class);
        $this->app->bind(CheckoutServiceInterface::class, CheckoutService::class);
        $this->app->bind(FavouriteServiceInterface::class, FavouriteService::class);
        $this->app->bind(HomeSectionServiceInterface::class, HomeSectionService::class);
        $this->app->bind(ProductRatingServiceInterface::class, ProductRatingService::class);
        $this->app->bind(IngredientCategoryServiceInterface::class, IngredientCategoryService::class);
        $this->app->bind(IngredientBrandServiceInterface::class, IngredientBrandService::class);
        $this->app->bind(IngredientServiceInterface::class, IngredientService::class);
        $this->app->bind(InventoryRefillRequestServiceInterface::class, InventoryRefillRequestService::class);
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
        $this->app->bind(RoleServiceInterface::class, RoleService::class);
        $this->app->bind(MenuCatalogServiceInterface::class, MenuCatalogService::class);
        $this->app->bind(MenuCategoryServiceInterface::class, MenuCategoryService::class);
        $this->app->bind(MenuItemServiceInterface::class, MenuItemService::class);
        $this->app->bind(OrderServiceInterface::class, OrderService::class);
        $this->app->bind(ProductCatalogServiceInterface::class, ProductCatalogService::class);
        $this->app->bind(ProductCategoryServiceInterface::class, ProductCategoryService::class);
        $this->app->bind(ProductFlavourServiceInterface::class, ProductFlavourService::class);
        $this->app->bind(ProductTagServiceInterface::class, ProductTagService::class);
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
        $this->app->bind(ProductReadinessServiceInterface::class, ProductReadinessService::class);
        $this->app->bind(RecipeCostingServiceInterface::class, RecipeCostingService::class);
        $this->app->bind(RecipeServiceInterface::class, RecipeService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(WebsiteSettingServiceInterface::class, WebsiteSettingService::class);
        $this->app->bind(SocialLinkServiceInterface::class, SocialLinkService::class);
    }
}
