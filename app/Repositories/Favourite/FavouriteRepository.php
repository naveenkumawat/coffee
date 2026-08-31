<?php

namespace App\Repositories\Favourite;

use App\Models\Product;
use App\Models\ProductFavourite;
use App\Models\User;
use App\Repositories\AbstractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FavouriteRepository extends AbstractRepository implements FavouriteRepositoryInterface
{
    public function __construct(
        protected ProductFavourite $model,
        protected Product $productModel,
    ) {}

    public function paginateForCustomer(User $customer, int $perPage = 20): LengthAwarePaginator
    {
        return $this->productModel->newQuery()
            ->whereHas('favouritedByCustomers', fn ($query) => $query->whereKey($customer->getKey()))
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with([
                'category',
                'flavours',
                'tags' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                'defaultVariant',
                'variants' => fn ($query) => $query->where('is_active', true)->where('is_available', true),
            ])
            ->withAvg('ratings as ratings_avg_rating', 'rating')
            ->withCount('ratings')
            ->orderByDesc(
                ProductFavourite::query()
                    ->select('created_at')
                    ->whereColumn('product_favourites.product_id', 'products.id')
                    ->where('customer_id', $customer->getKey())
                    ->limit(1)
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function productIdsForCustomer(User $customer): Collection
    {
        return $this->model->newQuery()
            ->where('customer_id', $customer->getKey())
            ->whereHas('product', function ($query): void {
                $query->where('is_active', true)
                    ->where('is_available', true)
                    ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('is_active', true));
            })
            ->orderByDesc('created_at')
            ->pluck('product_id');
    }

    public function findForCustomerProduct(User $customer, Product $product): ?ProductFavourite
    {
        return $this->model->newQuery()
            ->where('customer_id', $customer->getKey())
            ->where('product_id', $product->getKey())
            ->first();
    }

    public function add(User $customer, Product $product): ProductFavourite
    {
        return $this->model->newQuery()->firstOrCreate([
            'customer_id' => $customer->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function deleteFavourite(ProductFavourite $favourite): void
    {
        $favourite->delete();
    }
}
