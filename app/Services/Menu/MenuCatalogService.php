<?php

namespace App\Services\Menu;

use App\Repositories\Menu\MenuCategoryRepository;
use App\Repositories\Menu\MenuItemRepository;
use Illuminate\Support\Facades\Cache;

class MenuCatalogService
{
    public const PUBLIC_MENU_CACHE_KEY = 'menu.public.catalog';

    public const FEATURED_MENU_CACHE_KEY = 'menu.public.featured';

    public function __construct(
        protected MenuCategoryRepository $categories,
        protected MenuItemRepository $items,
    ) {}

    public function publicCatalog()
    {
        return Cache::remember(
            self::PUBLIC_MENU_CACHE_KEY,
            now()->addMinutes((int) config('coffee.cache.menu_ttl', 15)),
            fn () => $this->categories->activeWithItems(),
        );
    }

    public function featuredItems()
    {
        return Cache::remember(
            self::FEATURED_MENU_CACHE_KEY,
            now()->addMinutes((int) config('coffee.cache.menu_ttl', 15)),
            fn () => $this->items->featured(),
        );
    }

    public function flushPublicCache(): void
    {
        Cache::forget(self::PUBLIC_MENU_CACHE_KEY);
        Cache::forget(self::FEATURED_MENU_CACHE_KEY);
    }
}
