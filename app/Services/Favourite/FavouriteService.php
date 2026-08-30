<?php

namespace App\Services\Favourite;

use App\Models\Product;
use App\Models\User;
use App\Repositories\Favourite\FavouriteRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FavouriteService implements FavouriteServiceInterface
{
    public function __construct(
        protected FavouriteRepositoryInterface $favourites,
        protected ProductRepositoryInterface $products,
    ) {}

    public function paginateForCustomer(User $customer, int $perPage = 20): LengthAwarePaginator
    {
        return $this->favourites->paginateForCustomer($customer, $perPage);
    }

    public function productIdsForCustomer(User $customer): Collection
    {
        return $this->favourites->productIdsForCustomer($customer);
    }

    public function add(User $customer, int $productId): Product
    {
        $product = $this->resolveCustomerVisibleProduct($productId);
        $this->favourites->add($customer, $product);

        return $product->loadMissing([
            'category',
            'flavours',
            'defaultVariant',
            'variants' => fn ($query) => $query->where('is_active', true)->where('is_available', true),
        ]);
    }

    public function remove(User $customer, Product $product): void
    {
        $favourite = $this->favourites->findForCustomerProduct($customer, $product);

        if (! $favourite) {
            return;
        }

        if ((int) $favourite->customer_id !== (int) $customer->getKey()) {
            throw ValidationException::withMessages([
                'product' => 'This favourite does not belong to the authenticated customer.',
            ]);
        }

        $this->favourites->deleteFavourite($favourite);
    }

    public function isFavourited(User $customer, Product $product): bool
    {
        return $this->favourites->findForCustomerProduct($customer, $product) !== null;
    }

    protected function resolveCustomerVisibleProduct(int $productId): Product
    {
        $product = $this->products->findPublicById($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is not available.',
            ]);
        }

        return $product;
    }
}
