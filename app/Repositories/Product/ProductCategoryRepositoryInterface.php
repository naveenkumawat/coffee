<?php

namespace App\Repositories\Product;

use App\Models\ProductCategory;
use App\Transfers\Product\ProductCategoryFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductCategoryRepositoryInterface
{
    public function paginateForAdmin(ProductCategoryFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function allOptions(): array;

    public function activeOptions(): array;

    public function create(array $attributes): ProductCategory;

    public function update(ProductCategory $productCategory, array $attributes): ProductCategory;

    public function delete(ProductCategory $productCategory): void;

    public function slugExists(string $slug, ?int $ignoreId = null): bool;
}
