<?php

namespace App\Providers;

use App\Parsers\Menu\MenuCategoryParser;
use App\Parsers\Menu\MenuCategoryParserInterface;
use App\Parsers\Menu\MenuItemParser;
use App\Parsers\Menu\MenuItemParserInterface;
use Illuminate\Support\ServiceProvider;

class ParserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MenuCategoryParserInterface::class, MenuCategoryParser::class);
        $this->app->bind(MenuItemParserInterface::class, MenuItemParser::class);
    }
}
