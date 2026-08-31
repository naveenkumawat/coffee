<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rating\ProductRatingIndexRequest;
use App\Http\Requests\Rating\ProductRatingUpsertRequest;
use App\Http\Resources\Api\V1\MyProductRatingResource;
use App\Http\Resources\Api\V1\ProductRatingResource;
use App\Models\Product;
use App\Models\ProductRating;
use App\Services\Rating\ProductRatingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProductRatingController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected ProductRatingServiceInterface $ratings,
    ) {}

    public function index(ProductRatingIndexRequest $request, Product $product): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->integer('per_page', 10)));
        $payload = $this->ratings->detailPayload($request->user('web') ?? $request->user(), $product);
        $reviews = $this->ratings->paginatePublicReviews($product, $perPage);

        return $this->respondWithData([
            'rating_summary' => [
                'average' => $payload['rating_summary']['average'],
                'count' => $payload['rating_summary']['count'],
                'distribution' => $payload['rating_summary']['distribution'],
            ],
            'my_rating' => $payload['my_rating']
                ? (new MyProductRatingResource($payload['my_rating']))->resolve()
                : null,
            'can_rate' => $payload['can_rate'],
            'reviews' => ProductRatingResource::collection($reviews->items())->resolve(),
            'meta' => [
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ],
        ], 'Product ratings retrieved.');
    }

    public function store(ProductRatingUpsertRequest $request, Product $product): JsonResponse
    {
        $this->authorize('create', ProductRating::class);

        $rating = $this->ratings->upsert(
            $request->user(),
            $product,
            (int) $request->validated('rating'),
            $request->validated('review'),
        );

        $summary = $this->ratings->aggregate($product);

        return $this->respondWithData([
            'my_rating' => (new MyProductRatingResource($rating))->resolve(),
            'rating_summary' => [
                'average' => $summary['average'],
                'count' => $summary['count'],
            ],
            'can_rate' => true,
        ], 'Thanks for your rating.', 201);
    }

    public function update(ProductRatingUpsertRequest $request, Product $product): JsonResponse
    {
        $existing = $this->ratings->myRating($request->user(), $product);

        if ($existing) {
            $this->authorize('update', $existing);
        } else {
            $this->authorize('create', ProductRating::class);
        }

        $rating = $this->ratings->upsert(
            $request->user(),
            $product,
            (int) $request->validated('rating'),
            $request->validated('review'),
        );

        $summary = $this->ratings->aggregate($product);

        return $this->respondWithData([
            'my_rating' => (new MyProductRatingResource($rating))->resolve(),
            'rating_summary' => [
                'average' => $summary['average'],
                'count' => $summary['count'],
            ],
            'can_rate' => true,
        ], 'Rating updated.');
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $existing = $this->ratings->myRating($request->user(), $product);

        if ($existing) {
            $this->authorize('delete', $existing);
        }

        $this->ratings->deleteOwn($request->user(), $product);
        $summary = $this->ratings->aggregate($product);

        return $this->respondWithData([
            'my_rating' => null,
            'rating_summary' => [
                'average' => $summary['average'],
                'count' => $summary['count'],
            ],
            'can_rate' => $this->ratings->canRate($request->user(), $product),
        ], 'Rating removed.');
    }
}
