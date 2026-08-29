<?php

namespace App\Services\Menu;

use App\Repositories\Menu\MenuCategoryRepositoryInterface;
use App\Repositories\Menu\MenuItemRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class MenuCatalogService implements MenuCatalogServiceInterface
{
    public const PUBLIC_MENU_CACHE_KEY = 'menu.public.catalog';

    public const FEATURED_MENU_CACHE_KEY = 'menu.public.featured';

    public function __construct(
        protected MenuCategoryRepositoryInterface $categories,
        protected MenuItemRepositoryInterface $items,
    ) {}

    public function publicCatalog(): Collection
    {
        return Cache::remember(
            self::PUBLIC_MENU_CACHE_KEY,
            now()->addMinutes((int) config('coffee.cache.menu_ttl', 15)),
            fn () => $this->categories->activeWithItems(),
        );
    }

    public function featuredItems(): Collection
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
