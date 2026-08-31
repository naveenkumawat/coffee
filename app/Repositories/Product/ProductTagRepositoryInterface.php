<?php

namespace App\Repositories\Product;

use App\Models\ProductTag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductTagRepositoryInterface
{
    public function paginateForAdmin(int $perPage = 20): LengthAwarePaginator;

    /**
     * @return array<int, string>
     */
    public function allOptions(): array;

    /**
     * @return array<int, string>
     */
    public function activeOptions(): array;

    public function create(array $attributes): ProductTag;

    public function update(ProductTag $tag, array $attributes): ProductTag;

    public function delete(ProductTag $tag): void;

    public function slugExists(string $slug, ?int $ignoreId = null): bool;

    /**
     * @param  list<int>  $tagIds
     * @return Collection<int, ProductTag>
     */
    public function findActiveByIds(array $tagIds): Collection;
}
