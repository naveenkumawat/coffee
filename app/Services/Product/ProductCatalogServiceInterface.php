<?php

namespace App\Services\Product;

use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductCatalogServiceInterface
{
    public function publicCatalog(): Collection;

    public function featuredProducts(): Collection;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPublicProducts(array $filters = []): Collection;

    /**
     * Cached customer-safe product arrays for the public menu API.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function listPublicProductPayload(array $filters = []): array;

    public function paginatePublicProducts(array $filters = [], int $perPage = 12): LengthAwarePaginator;

    public function paginatePublicVariants(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findPublicProduct(int $productId): ?Product;

    public function publicCatalogVersion(): string;

    public function publicCatalogUpdatedAt(): CarbonInterface;

    public function flushPublicCache(): void;
}
