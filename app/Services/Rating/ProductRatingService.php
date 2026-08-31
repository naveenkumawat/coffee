<?php

namespace App\Services\Rating;

use App\Models\Product;
use App\Models\ProductRating;
use App\Models\User;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\Rating\ProductRatingRepositoryInterface;
use App\Services\Product\ProductCatalogServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductRatingService implements ProductRatingServiceInterface
{
    public function __construct(
        protected ProductRatingRepositoryInterface $ratings,
        protected ProductRepositoryInterface $products,
        protected ProductCatalogServiceInterface $catalog,
    ) {}

    public function canRate(User $customer, Product $product): bool
    {
        return $this->ratings->customerHasCompletedPurchase($customer, $product);
    }

    public function myRating(User $customer, Product $product): ?ProductRating
    {
        return $this->ratings->findForCustomerProduct($customer, $product);
    }

    /**
     * @return array{average: float|null, count: int, distribution: array<int, int>}
     */
    public function aggregate(Product $product): array
    {
        return $this->ratings->aggregateForProduct($product);
    }

    public function paginatePublicReviews(Product $product, int $perPage = 10): LengthAwarePaginator
    {
        return $this->ratings->paginatePublicReviews($product, $perPage);
    }

    /**
     * @return array{
     *     rating_summary: array{average: float|null, count: int, distribution: array<int, int>},
     *     my_rating: ProductRating|null,
     *     can_rate: bool
     * }
     */
    public function detailPayload(?User $customer, Product $product): array
    {
        return [
            'rating_summary' => $this->aggregate($product),
            'my_rating' => $customer ? $this->myRating($customer, $product) : null,
            'can_rate' => $customer ? $this->canRate($customer, $product) : false,
        ];
    }

    public function upsert(User $customer, Product $product, int $rating, ?string $review): ProductRating
    {
        $qualifyingOrderId = $this->ratings->findQualifyingCompletedOrderId($customer, $product);

        if ($qualifyingOrderId === null) {
            throw ValidationException::withMessages([
                'product' => 'You can rate this product only after a completed purchase.',
            ]);
        }

        $cleanReview = $this->normalizeReview($review);

        $ratingModel = $this->ratings->upsertForCustomer(
            $customer,
            $product,
            $rating,
            $cleanReview,
            $qualifyingOrderId,
        );

        $this->catalog->flushPublicCache();

        return $ratingModel;
    }

    public function deleteOwn(User $customer, Product $product): void
    {
        $existing = $this->ratings->findForCustomerProduct($customer, $product);

        if (! $existing) {
            return;
        }

        if ((int) $existing->customer_id !== (int) $customer->getKey()) {
            throw ValidationException::withMessages([
                'product' => 'You can only delete your own rating.',
            ]);
        }

        $this->ratings->deleteForCustomer($existing);
        $this->catalog->flushPublicCache();
    }

    /**
     * @param  array{search?: string|null, product_id?: int|null, rating?: int|null, is_public?: bool|null}  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->ratings->paginateForAdmin($filters, $perPage);
    }

    public function hideReview(ProductRating $rating, User $moderator): ProductRating
    {
        $updated = $this->ratings->setPublicVisibility($rating, false, $moderator);
        $this->catalog->flushPublicCache();

        return $updated;
    }

    public function publishReview(ProductRating $rating, User $moderator): ProductRating
    {
        $updated = $this->ratings->setPublicVisibility($rating, true, $moderator);
        $this->catalog->flushPublicCache();

        return $updated;
    }

    public function deleteAsAdmin(ProductRating $rating): void
    {
        $this->ratings->forceDeleteRating($rating);
        $this->catalog->flushPublicCache();
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $productIds
     * @return array<int, array{average: float|null, count: int}>
     */
    public function summaryMapForProductIds(Collection|array $productIds): array
    {
        $ids = $productIds instanceof Collection ? $productIds : collect($productIds);

        return $this->ratings->summaryMapForProductIds($ids->map(fn ($id) => (int) $id)->unique()->values());
    }

    protected function normalizeReview(?string $review): ?string
    {
        if ($review === null) {
            return null;
        }

        $trimmed = trim($review);

        return $trimmed === '' ? null : $trimmed;
    }
}
