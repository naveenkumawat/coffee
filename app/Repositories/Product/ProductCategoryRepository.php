<?php

namespace App\Repositories\Product;

use App\Models\ProductCategory;
use App\Repositories\AbstractRepository;
use App\Transfers\Product\ProductCategoryFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductCategoryRepository extends AbstractRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(
        protected ProductCategory $model,
    ) {}

    public function paginateForAdmin(ProductCategoryFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount('products')
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters->getStatus() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters->getStatus() === 'inactive', fn ($query) => $query->where('is_active', false))
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

    public function publicCatalog(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true)->where('is_available', true))
            ->withCount([
                'products' => fn ($query) => $query->where('is_active', true)->where('is_available', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function create(array $attributes): ProductCategory
    {
        /** @var ProductCategory $productCategory */
        $productCategory = $this->persist($this->model->newInstance(), $attributes);

        return $productCategory;
    }

    public function update(ProductCategory $productCategory, array $attributes): ProductCategory
    {
        /** @var ProductCategory $productCategory */
        $productCategory = $this->persist($productCategory, $attributes);

        return $productCategory;
    }

    public function delete(ProductCategory $productCategory): void
    {
        $this->remove($productCategory);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
