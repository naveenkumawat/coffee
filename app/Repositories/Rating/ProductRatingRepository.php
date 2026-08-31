<?php

namespace App\Repositories\Rating;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRating;
use App\Models\User;
use App\Repositories\AbstractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductRatingRepository extends AbstractRepository implements ProductRatingRepositoryInterface
{
    public function __construct(
        protected ProductRating $model,
        protected Order $orderModel,
    ) {}

    public function findForCustomerProduct(User $customer, Product $product): ?ProductRating
    {
        return $this->model->newQuery()
            ->where('customer_id', $customer->getKey())
            ->where('product_id', $product->getKey())
            ->first();
    }

    public function findQualifyingCompletedOrderId(User $customer, Product $product): ?int
    {
        $orderId = $this->orderModel->newQuery()
            ->where('customer_id', $customer->getKey())
            ->where('status', OrderStatus::Completed)
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->getKey()))
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->value('id');

        return $orderId !== null ? (int) $orderId : null;
    }

    public function customerHasCompletedPurchase(User $customer, Product $product): bool
    {
        return $this->findQualifyingCompletedOrderId($customer, $product) !== null;
    }

    /**
     * @return array{average: float|null, count: int, distribution: array<int, int>}
     */
    public function aggregateForProduct(Product $product): array
    {
        $stats = $this->model->newQuery()
            ->where('product_id', $product->getKey())
            ->selectRaw('COUNT(*) as rating_count, AVG(rating) as rating_average')
            ->first();

        $distributionRows = $this->model->newQuery()
            ->where('product_id', $product->getKey())
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $distribution[$star] = (int) ($distributionRows[$star] ?? 0);
        }

        $count = (int) ($stats->rating_count ?? 0);
        $average = $count > 0 ? round((float) $stats->rating_average, 1) : null;

        return [
            'average' => $average,
            'count' => $count,
            'distribution' => $distribution,
        ];
    }

    public function paginatePublicReviews(Product $product, int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with(['customer:id,name'])
            ->where('product_id', $product->getKey())
            ->where('is_public', true)
            ->whereNotNull('review')
            ->where('review', '!=', '')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, product_id?: int|null, rating?: int|null, is_public?: bool|null}  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with([
                'product:id,name,slug',
                'customer:id,name,email',
                'qualifyingOrder:id,order_number,completed_at',
                'moderator:id,name',
            ])
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = trim((string) $filters['search']);

                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('review', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when(filled($filters['product_id'] ?? null), fn ($query) => $query->where('product_id', (int) $filters['product_id']))
            ->when(filled($filters['rating'] ?? null), fn ($query) => $query->where('rating', (int) $filters['rating']))
            ->when(array_key_exists('is_public', $filters) && $filters['is_public'] !== null, function ($query) use ($filters): void {
                $query->where('is_public', (bool) $filters['is_public']);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function upsertForCustomer(
        User $customer,
        Product $product,
        int $rating,
        ?string $review,
        int $qualifyingOrderId,
    ): ProductRating {
        /** @var ProductRating $record */
        $record = $this->model->newQuery()
            ->withTrashed()
            ->firstOrNew([
                'customer_id' => $customer->getKey(),
                'product_id' => $product->getKey(),
            ]);

        if ($record->trashed()) {
            $record->restore();
        }

        $record->fill([
            'rating' => $rating,
            'review' => $review,
            'qualifying_order_id' => $qualifyingOrderId,
            'is_public' => true,
            'moderated_at' => null,
            'moderated_by' => null,
        ]);
        $record->save();

        return $record->fresh(['customer', 'product']) ?? $record;
    }

    public function deleteForCustomer(ProductRating $rating): void
    {
        $rating->delete();
    }

    public function setPublicVisibility(ProductRating $rating, bool $isPublic, User $moderator): ProductRating
    {
        $rating->fill([
            'is_public' => $isPublic,
            'moderated_at' => now(),
            'moderated_by' => $moderator->getKey(),
        ]);
        $rating->save();

        return $rating->fresh(['customer', 'product', 'moderator']) ?? $rating;
    }

    public function forceDeleteRating(ProductRating $rating): void
    {
        $rating->forceDelete();
    }

    /**
     * @param  Collection<int, int>  $productIds
     * @return array<int, array{average: float|null, count: int}>
     */
    public function summaryMapForProductIds(Collection $productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        $rows = $this->model->newQuery()
            ->whereIn('product_id', $productIds->all())
            ->selectRaw('product_id, COUNT(*) as rating_count, AVG(rating) as rating_average')
            ->groupBy('product_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $count = (int) $row->rating_count;
            $map[(int) $row->product_id] = [
                'average' => $count > 0 ? round((float) $row->rating_average, 1) : null,
                'count' => $count,
            ];
        }

        return $map;
    }
}
