<?php

namespace App\Services\Product;

use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Repositories\Product\ProductRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductCatalogService implements ProductCatalogServiceInterface
{
    public const PUBLIC_PRODUCT_CACHE_KEY = 'product.public.catalog';

    public const FEATURED_PRODUCT_CACHE_KEY = 'product.public.featured';

    public const PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY = 'product.public.products.payload';

    public const PUBLIC_CATALOG_VERSION_KEY = 'product.public.catalog.version';

    public const PUBLIC_CATALOG_UPDATED_AT_KEY = 'product.public.catalog.updated_at';

    /** @deprecated Use PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY */
    public const PUBLIC_PRODUCTS_LIST_CACHE_KEY = self::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY;

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

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function listPublicProductPayload(array $filters = []): array
    {
        return $this->filterPublicProductPayload($this->cachedPublicProductPayload(), $filters);
    }

    public function listPublicProducts(array $filters = []): Collection
    {
        // Kept for callers that still expect models; prefer listPublicProductPayload for HTTP.
        $ids = collect($this->listPublicProductPayload($filters))->pluck('id')->all();

        if ($ids === []) {
            return new Collection;
        }

        $products = $this->products->listPublicProducts()->keyBy('id');

        return (new Collection($ids))
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->values();
    }

    public function paginatePublicProducts(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->products->paginatePublic($filters, $perPage);
    }

    public function paginatePublicVariants(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->products->paginatePublicVariants($filters, $perPage);
    }

    public function findPublicProduct(int $productId): ?Product
    {
        return $this->products->findPublicById($productId);
    }

    public function publicCatalogVersion(): string
    {
        return (string) Cache::get(self::PUBLIC_CATALOG_VERSION_KEY, '0');
    }

    public function publicCatalogUpdatedAt(): CarbonInterface
    {
        $raw = Cache::get(self::PUBLIC_CATALOG_UPDATED_AT_KEY);

        if (is_string($raw) && filled($raw)) {
            return Carbon::parse($raw);
        }

        return now()->startOfMinute();
    }

    public function flushPublicCache(): void
    {
        Cache::forget(self::PUBLIC_PRODUCT_CACHE_KEY);
        Cache::forget(self::FEATURED_PRODUCT_CACHE_KEY);
        Cache::forget(self::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY);
        Cache::forever(self::PUBLIC_CATALOG_VERSION_KEY, (string) Str::uuid());
        Cache::forever(self::PUBLIC_CATALOG_UPDATED_AT_KEY, now()->toIso8601String());
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function cachedPublicProductPayload(): array
    {
        /** @var list<array<string, mixed>> $payload */
        $payload = Cache::remember(
            self::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY,
            now()->addMinutes((int) config('coffee.cache.menu_ttl', 15)),
            function (): array {
                $products = $this->products->listPublicProducts();

                // Nested JsonResource collections must be plain arrays before caching —
                // PHP serialize leaves AnonymousResourceCollection as incomplete classes,
                // which strip variant is_available on the next catalogue read.
                $resolved = ProductResource::collection($products)->resolve();
                /** @var list<array<string, mixed>> $pure */
                $pure = json_decode(json_encode($resolved), true) ?? [];

                return $pure;
            },
        );

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    protected function filterPublicProductPayload(array $products, array $filters): array
    {
        $search = filled($filters['search'] ?? null) ? mb_strtolower(trim((string) $filters['search'])) : null;
        $categoryIds = $this->normalizedIds($filters, 'product_category_id', 'product_category_ids');
        $flavourIds = $this->normalizedIds($filters, 'product_flavour_id', 'product_flavour_ids');

        $filtered = array_values(array_filter($products, function (array $product) use ($search, $categoryIds, $flavourIds, $filters): bool {
            if ($categoryIds !== []) {
                $categoryId = (int) ($product['category']['id'] ?? 0);

                if ($categoryId === 0 || ! in_array($categoryId, $categoryIds, true)) {
                    return false;
                }
            }

            if ($flavourIds !== []) {
                $productFlavourIds = collect($product['flavours'] ?? [])
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                if (count(array_intersect($flavourIds, $productFlavourIds)) === 0) {
                    return false;
                }
            }

            if (($filters['featured'] ?? null) === 'featured' && empty($product['is_featured'])) {
                return false;
            }

            if (($filters['new'] ?? null) === 'new' && empty($product['is_new'])) {
                return false;
            }

            if (($filters['bestseller'] ?? null) === 'bestseller' && empty($product['is_bestseller'])) {
                return false;
            }

            if ($search === null) {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', array_filter([
                (string) ($product['name'] ?? ''),
                (string) ($product['short_description'] ?? ''),
                (string) ($product['description'] ?? ''),
                (string) ($product['customer_ingredient_summary'] ?? ''),
            ])));

            return str_contains($haystack, $search);
        }));

        if (($filters['sort'] ?? null) === 'rating_high_to_low') {
            usort($filtered, function (array $left, array $right): int {
                $avgCmp = ((float) ($right['rating_summary']['average'] ?? 0)) <=> ((float) ($left['rating_summary']['average'] ?? 0));

                if ($avgCmp !== 0) {
                    return $avgCmp;
                }

                return ((int) ($right['rating_summary']['count'] ?? 0)) <=> ((int) ($left['rating_summary']['count'] ?? 0));
            });
        }

        if (($filters['sort'] ?? null) === 'most_reviewed') {
            usort($filtered, function (array $left, array $right): int {
                $countCmp = ((int) ($right['rating_summary']['count'] ?? 0)) <=> ((int) ($left['rating_summary']['count'] ?? 0));

                if ($countCmp !== 0) {
                    return $countCmp;
                }

                return ((float) ($right['rating_summary']['average'] ?? 0)) <=> ((float) ($left['rating_summary']['average'] ?? 0));
            });
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    protected function normalizedIds(array $filters, string $singularKey, string $pluralKey): array
    {
        return collect($filters[$pluralKey] ?? [])
            ->when(filled($filters[$singularKey] ?? null), fn ($ids) => $ids->push($filters[$singularKey]))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
