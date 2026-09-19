<?php

namespace App\Services\PublicCache;

use App\Enums\HomeSectionPlacement;
use App\Events\Realtime\PublicCacheInvalidated;
use App\Services\CafeAvailability\CafeAvailabilityService;
use App\Services\Menu\MenuCatalogService;
use App\Services\Product\ProductCatalogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicCacheVersionService implements PublicCacheVersionServiceInterface
{
    public const VERSION_KEY = 'public.cache.version';

    public const INVALIDATED_AT_KEY = 'public.cache.invalidated_at';

    public const REASON_KEY = 'public.cache.reason';

    public const ACTOR_KEY = 'public.cache.actor_id';

    public function snapshot(): array
    {
        $version = $this->currentVersion();

        $invalidatedAt = Cache::get(self::INVALIDATED_AT_KEY);
        $reason = Cache::get(self::REASON_KEY);

        return [
            'cache_version' => $version,
            'catalog_version' => $version,
            'content_version' => $version,
            'media_version' => $version,
            'invalidated_at' => is_string($invalidatedAt) && $invalidatedAt !== '' ? $invalidatedAt : null,
            'reason' => is_string($reason) && $reason !== '' ? $reason : null,
        ];
    }

    public function currentVersion(): string
    {
        $version = Cache::get(self::VERSION_KEY);

        if (is_string($version) && $version !== '') {
            return $version;
        }

        $version = (string) Str::uuid();
        Cache::forever(self::VERSION_KEY, $version);

        return $version;
    }

    public function bump(string $reason, ?int $actorId = null): string
    {
        $version = (string) Str::uuid();

        Cache::forever(self::VERSION_KEY, $version);
        Cache::forever(self::INVALIDATED_AT_KEY, now()->toIso8601String());
        Cache::forever(self::REASON_KEY, $reason);
        Cache::forever(self::ACTOR_KEY, $actorId);

        PublicCacheInvalidated::dispatch($version);

        return $version;
    }

    public function forgetServerPublicCaches(): void
    {
        Cache::forget(ProductCatalogService::PUBLIC_PRODUCT_CACHE_KEY);
        Cache::forget(ProductCatalogService::FEATURED_PRODUCT_CACHE_KEY);
        Cache::forget(ProductCatalogService::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY);
        Cache::forget(MenuCatalogService::PUBLIC_MENU_CACHE_KEY);
        Cache::forget(MenuCatalogService::FEATURED_MENU_CACHE_KEY);
        Cache::forget(CafeAvailabilityService::PUBLIC_CACHE_KEY);
        Cache::forget('campaigns.active.popup.v1');
        Cache::forget('campaigns.active.banner.v1');
        Cache::forget('campaigns.active.inline.v1');
        Cache::forget('campaigns.active.landing.v1');

        foreach (HomeSectionPlacement::cases() as $placement) {
            Cache::forget('merchandising.sections.'.$placement->value.'.v1');
        }
    }

    public function invalidate(string $reason, ?int $actorId = null): string
    {
        $this->forgetServerPublicCaches();
        Cache::forever(ProductCatalogService::PUBLIC_CATALOG_VERSION_KEY, (string) Str::uuid());
        Cache::forever(ProductCatalogService::PUBLIC_CATALOG_UPDATED_AT_KEY, now()->toIso8601String());

        return $this->bump($reason, $actorId);
    }
}
