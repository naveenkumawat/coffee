<?php

namespace App\Repositories\Product;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Transfers\Product\ProductFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    public function paginateForAdmin(ProductFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function paginateForBarista(ProductFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function paginateForCategory(ProductCategory $productCategory, int $perPage = 12): LengthAwarePaginator;

    public function paginateForFlavour(ProductFlavour $productFlavour, int $perPage = 12): LengthAwarePaginator;

    public function publicCatalog(): Collection;

    public function featured(int $limit = 4): Collection;

    public function paginatePublic(array $filters = [], int $perPage = 12): LengthAwarePaginator;

    public function paginatePublicVariants(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findPublicById(int $productId): ?Product;

    public function create(array $attributes): Product;

    public function update(Product $product, array $attributes): Product;

    public function delete(Product $product): void;

    public function syncFlavours(Product $product, array $flavourIds): void;

    public function syncTags(Product $product, array $tagIds): void;

    public function replaceVariants(Product $product, array $variants): Product;

    public function slugExists(string $slug, ?int $ignoreId = null): bool;

    public function skuExists(?string $sku, ?int $ignoreId = null): bool;
}
