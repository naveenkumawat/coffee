<?php

namespace App\Repositories\Favourite;

use App\Models\Product;
use App\Models\ProductFavourite;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface FavouriteRepositoryInterface
{
    public function paginateForCustomer(User $customer, int $perPage = 20): LengthAwarePaginator;

    public function productIdsForCustomer(User $customer): Collection;

    public function findForCustomerProduct(User $customer, Product $product): ?ProductFavourite;

    public function add(User $customer, Product $product): ProductFavourite;

    public function deleteFavourite(ProductFavourite $favourite): void;
}
