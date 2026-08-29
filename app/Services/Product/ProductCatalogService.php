<?php

namespace App\Services\Product;

use App\Repositories\Product\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ProductCatalogService implements ProductCatalogServiceInterface
{
    public const PUBLIC_PRODUCT_CACHE_KEY = 'product.public.catalog';

    public const FEATURED_PRODUCT_CACHE_KEY = 'product.public.featured';

    public function __construct(
        protected ProductRepositoryInterface $products,
    ) {}

    public function publicCatalog(): Collection
    {
        return Cache::remember(
            self::PUBLIC_PRODUCT_CACHE_KEY,
            now()->addMinutes((int) config('coffee.cache.menu_ttl', 15)),
            fn (): Collection => $this->products->publicCatalog(),
        );
    }

    public function featuredProducts(): Collection
    {
        return Cache::remember(
            self::FEATURED_PRODUCT_CACHE_KEY,
            now()->addMinutes((int) config('coffee.cache.menu_ttl', 15)),
            fn (): Collection => $this->products->featured(),
        );
    }

    public function flushPublicCache(): void
    {
        Cache::forget(self::PUBLIC_PRODUCT_CACHE_KEY);
        Cache::forget(self::FEATURED_PRODUCT_CACHE_KEY);
    }
}
