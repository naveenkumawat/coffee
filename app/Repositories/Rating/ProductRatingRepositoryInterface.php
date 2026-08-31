<?php

namespace App\Repositories\Rating;

use App\Models\Product;
use App\Models\ProductRating;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductRatingRepositoryInterface
{
    public function findForCustomerProduct(User $customer, Product $product): ?ProductRating;

    public function findQualifyingCompletedOrderId(User $customer, Product $product): ?int;

    public function customerHasCompletedPurchase(User $customer, Product $product): bool;

    /**
     * @return array{average: float|null, count: int, distribution: array<int, int>}
     */
    public function aggregateForProduct(Product $product): array;

    public function paginatePublicReviews(Product $product, int $perPage = 10): LengthAwarePaginator;

    /**
     * @param  array{search?: string|null, product_id?: int|null, rating?: int|null, is_public?: bool|null}  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function upsertForCustomer(
        User $customer,
        Product $product,
        int $rating,
        ?string $review,
        int $qualifyingOrderId,
    ): ProductRating;

    public function deleteForCustomer(ProductRating $rating): void;

    public function setPublicVisibility(ProductRating $rating, bool $isPublic, User $moderator): ProductRating;

    public function forceDeleteRating(ProductRating $rating): void;

    /**
     * @param  Collection<int, int>  $productIds
     * @return array<int, array{average: float|null, count: int}>
     */
    public function summaryMapForProductIds(Collection $productIds): array;
}
