<?php

namespace App\Services\Favourite;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface FavouriteServiceInterface
{
    public function paginateForCustomer(User $customer, int $perPage = 20): LengthAwarePaginator;

    public function productIdsForCustomer(User $customer): Collection;

    public function add(User $customer, int $productId): Product;

    public function remove(User $customer, Product $product): void;

    public function isFavourited(User $customer, Product $product): bool;
}
