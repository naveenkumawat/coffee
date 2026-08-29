<?php

namespace App\Repositories\Product;

use App\Models\ProductFlavour;
use App\Repositories\AbstractRepository;
use App\Transfers\Product\ProductFlavourFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductFlavourRepository extends AbstractRepository implements ProductFlavourRepositoryInterface
{
    public function __construct(
        protected ProductFlavour $model,
    ) {}

    public function paginateForAdmin(ProductFlavourFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount(['products', 'categories'])
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
            ->when($filters->getProductCategoryId(), fn ($query) => $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->whereKey($filters->getProductCategoryId())))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function allOptions(): array
    {
        return $this->model->newQuery()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function activeOptions(): array
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function create(array $attributes): ProductFlavour
    {
        /** @var ProductFlavour $productFlavour */
        $productFlavour = $this->persist($this->model->newInstance(), $attributes);

        return $productFlavour;
    }

    public function update(ProductFlavour $productFlavour, array $attributes): ProductFlavour
    {
        /** @var ProductFlavour $productFlavour */
        $productFlavour = $this->persist($productFlavour, $attributes);

        return $productFlavour;
    }

    public function delete(ProductFlavour $productFlavour): void
    {
        $this->remove($productFlavour);
    }

    public function syncCategories(ProductFlavour $productFlavour, array $categoryIds): void
    {
        $productFlavour->categories()->sync($categoryIds);
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
