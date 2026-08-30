<?php

namespace App\Repositories\Product;

use App\Models\ProductFlavour;
use App\Transfers\Product\ProductFlavourFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductFlavourRepositoryInterface
{
    public function paginateForAdmin(ProductFlavourFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function allOptions(): array;

    public function activeOptions(): array;

    public function publicCatalog(): Collection;

    public function create(array $attributes): ProductFlavour;

    public function update(ProductFlavour $productFlavour, array $attributes): ProductFlavour;

    public function delete(ProductFlavour $productFlavour): void;

    public function syncCategories(ProductFlavour $productFlavour, array $categoryIds): void;

    public function slugExists(string $slug, ?int $ignoreId = null): bool;
}
