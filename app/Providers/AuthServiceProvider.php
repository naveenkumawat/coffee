<?php

namespace App\Providers;

use App\Models\CafeClosure;
use App\Models\CafeTable;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerReferral;
use App\Models\DiningSession;
use App\Models\HomeSection;
use App\Models\Ingredient;
use App\Models\IngredientBrand;
use App\Models\IngredientCategory;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderPreparation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFavourite;
use App\Models\ProductFlavour;
use App\Models\ProductRating;
use App\Models\ProductTag;
use App\Models\Promotion;
use App\Models\Recipe;
use App\Models\SocialLink;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Policies\CafeClosurePolicy;
use App\Policies\CafeTablePolicy;
use App\Policies\CartItemPolicy;
use App\Policies\CartPolicy;
use App\Policies\CustomerReferralPolicy;
use App\Policies\DiningSessionPolicy;
use App\Policies\HomeSectionPolicy;
use App\Policies\IngredientBrandPolicy;
use App\Policies\IngredientCategoryPolicy;
use App\Policies\IngredientPolicy;
use App\Policies\InventoryRefillRequestPolicy;
use App\Policies\InventoryTransactionPolicy;
use App\Policies\MenuCategoryPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrderPreparationPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductFavouritePolicy;
use App\Policies\ProductFlavourPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductRatingPolicy;
use App\Policies\ProductTagPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\RecipePolicy;
use App\Policies\SocialLinkPolicy;
use App\Policies\UserPolicy;
use App\Policies\WebsiteSettingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Cart::class => CartPolicy::class,
        CartItem::class => CartItemPolicy::class,
        CustomerReferral::class => CustomerReferralPolicy::class,
        CafeClosure::class => CafeClosurePolicy::class,
        CafeTable::class => CafeTablePolicy::class,
        DiningSession::class => DiningSessionPolicy::class,
        HomeSection::class => HomeSectionPolicy::class,
        Ingredient::class => IngredientPolicy::class,
        IngredientBrand::class => IngredientBrandPolicy::class,
        IngredientCategory::class => IngredientCategoryPolicy::class,
        InventoryRefillRequest::class => InventoryRefillRequestPolicy::class,
        InventoryTransaction::class => InventoryTransactionPolicy::class,
        MenuCategory::class => MenuCategoryPolicy::class,
        MenuItem::class => MenuItemPolicy::class,
        Order::class => OrderPolicy::class,
        OrderPreparation::class => OrderPreparationPolicy::class,
        Product::class => ProductPolicy::class,
        ProductCategory::class => ProductCategoryPolicy::class,
        ProductFavourite::class => ProductFavouritePolicy::class,
        ProductFlavour::class => ProductFlavourPolicy::class,
        ProductRating::class => ProductRatingPolicy::class,
        ProductTag::class => ProductTagPolicy::class,
        Promotion::class => PromotionPolicy::class,
        Recipe::class => RecipePolicy::class,
        SocialLink::class => SocialLinkPolicy::class,
        User::class => UserPolicy::class,
        WebsiteSetting::class => WebsiteSettingPolicy::class,
    ];

    public function boot(): void
    {
        Gate::before(fn ($user, string $ability) => $user->hasRole('owner') ? true : null);
    }
}
