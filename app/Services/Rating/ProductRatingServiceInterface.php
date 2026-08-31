<?php

namespace App\Services\Rating;

use App\Models\Product;
use App\Models\ProductRating;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductRatingServiceInterface
{
    public function canRate(User $customer, Product $product): bool;

    public function myRating(User $customer, Product $product): ?ProductRating;

    /**
     * @return array{average: float|null, count: int, distribution: array<int, int>}
     */
    public function aggregate(Product $product): array;

    public function paginatePublicReviews(Product $product, int $perPage = 10): LengthAwarePaginator;

    /**
     * @return array{
     *     rating_summary: array{average: float|null, count: int, distribution: array<int, int>},
     *     my_rating: ProductRating|null,
     *     can_rate: bool
     * }
     */
    public function detailPayload(?User $customer, Product $product): array;

    public function upsert(User $customer, Product $product, int $rating, ?string $review): ProductRating;

    public function deleteOwn(User $customer, Product $product): void;

    /**
     * @param  array{search?: string|null, product_id?: int|null, rating?: int|null, is_public?: bool|null}  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function hideReview(ProductRating $rating, User $moderator): ProductRating;

    public function publishReview(ProductRating $rating, User $moderator): ProductRating;

    public function deleteAsAdmin(ProductRating $rating): void;

    /**
     * @param  Collection<int, int>|array<int, int>  $productIds
     * @return array<int, array{average: float|null, count: int}>
     */
    public function summaryMapForProductIds(Collection|array $productIds): array;
}
