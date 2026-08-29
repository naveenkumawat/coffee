<?php

namespace App\Providers;

use App\Parsers\Menu\MenuCategoryParser;
use App\Parsers\Menu\MenuCategoryParserInterface;
use App\Parsers\Menu\MenuItemParser;
use App\Parsers\Menu\MenuItemParserInterface;
use App\Parsers\User\UserParser;
use App\Parsers\User\UserParserInterface;
use Illuminate\Support\ServiceProvider;

class ParserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MenuCategoryParserInterface::class, MenuCategoryParser::class);
        $this->app->bind(MenuItemParserInterface::class, MenuItemParser::class);
        $this->app->bind(UserParserInterface::class, UserParser::class);
    }
}
