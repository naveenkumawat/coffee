<?php

namespace App\Providers;

use App\Parsers\Ingredient\IngredientBrandParser;
use App\Parsers\Ingredient\IngredientBrandParserInterface;
use App\Parsers\Ingredient\IngredientCategoryParser;
use App\Parsers\Ingredient\IngredientCategoryParserInterface;
use App\Parsers\Ingredient\IngredientParser;
use App\Parsers\Ingredient\IngredientParserInterface;
use App\Parsers\Inventory\InventoryParser;
use App\Parsers\Inventory\InventoryParserInterface;
use App\Parsers\Inventory\InventoryRefillRequestParser;
use App\Parsers\Inventory\InventoryRefillRequestParserInterface;
use App\Parsers\Menu\MenuCategoryParser;
use App\Parsers\Menu\MenuCategoryParserInterface;
use App\Parsers\Menu\MenuItemParser;
use App\Parsers\Menu\MenuItemParserInterface;
use App\Parsers\Order\OrderParser;
use App\Parsers\Order\OrderParserInterface;
use App\Parsers\Product\ProductCategoryParser;
use App\Parsers\Product\ProductCategoryParserInterface;
use App\Parsers\Product\ProductFlavourParser;
use App\Parsers\Product\ProductFlavourParserInterface;
use App\Parsers\Product\ProductParser;
use App\Parsers\Product\ProductParserInterface;
use App\Parsers\Recipe\RecipeParser;
use App\Parsers\Recipe\RecipeParserInterface;
use App\Parsers\User\UserParser;
use App\Parsers\User\UserParserInterface;
use Illuminate\Support\ServiceProvider;

class ParserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IngredientCategoryParserInterface::class, IngredientCategoryParser::class);
        $this->app->bind(IngredientBrandParserInterface::class, IngredientBrandParser::class);
        $this->app->bind(IngredientParserInterface::class, IngredientParser::class);
        $this->app->bind(InventoryRefillRequestParserInterface::class, InventoryRefillRequestParser::class);
        $this->app->bind(InventoryParserInterface::class, InventoryParser::class);
        $this->app->bind(MenuCategoryParserInterface::class, MenuCategoryParser::class);
        $this->app->bind(MenuItemParserInterface::class, MenuItemParser::class);
        $this->app->bind(OrderParserInterface::class, OrderParser::class);
        $this->app->bind(ProductCategoryParserInterface::class, ProductCategoryParser::class);
        $this->app->bind(ProductFlavourParserInterface::class, ProductFlavourParser::class);
        $this->app->bind(ProductParserInterface::class, ProductParser::class);
        $this->app->bind(RecipeParserInterface::class, RecipeParser::class);
        $this->app->bind(UserParserInterface::class, UserParser::class);
    }
}
