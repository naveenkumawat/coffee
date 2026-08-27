<?php

namespace App\Providers;

use App\Events\Menu\MenuCategorySaved;
use App\Events\Menu\MenuItemSaved;
use App\Listeners\Menu\FlushMenuCatalogCache;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MenuCategorySaved::class => [
            FlushMenuCatalogCache::class,
        ],
        MenuItemSaved::class => [
            FlushMenuCatalogCache::class,
        ],
    ];
}
