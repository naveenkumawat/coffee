<?php

namespace App\Repositories\Product;

use App\Models\ProductTag;
use App\Repositories\AbstractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductTagRepository extends AbstractRepository implements ProductTagRepositoryInterface
{
    public function __construct(
        protected ProductTag $model,
    ) {}

    public function paginateForAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function allOptions(): array
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function activeOptions(): array
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function create(array $attributes): ProductTag
    {
        /** @var ProductTag $tag */
        $tag = $this->persist($this->model->newInstance(), $attributes);

        return $tag;
    }

    public function update(ProductTag $tag, array $attributes): ProductTag
    {
        /** @var ProductTag $tag */
        $tag = $this->persist($tag, $attributes);

        return $tag;
    }

    public function delete(ProductTag $tag): void
    {
        $this->remove($tag);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return $this->model->newQuery()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function findActiveByIds(array $tagIds): Collection
    {
        if ($tagIds === []) {
            return new Collection;
        }

        return $this->model->newQuery()
            ->where('is_active', true)
            ->whereKey($tagIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
