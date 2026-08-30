<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Favourite\FavouriteStoreRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\ProductFavourite;
use App\Services\Favourite\FavouriteServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerFavouriteController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected FavouriteServiceInterface $favourites,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductFavourite::class);

        $perPage = max(1, min(50, (int) $request->integer('per_page', 20)));

        return $this->respondWithPaginator(
            $this->favourites->paginateForCustomer($request->user(), $perPage),
            ProductResource::class,
            'Favourites retrieved.',
        );
    }

    public function ids(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductFavourite::class);

        return $this->respondWithData([
            'ids' => $this->favourites->productIdsForCustomer($request->user())->values()->all(),
        ], 'Favourite product ids retrieved.');
    }

    public function store(FavouriteStoreRequest $request): JsonResponse
    {
        $this->authorize('create', ProductFavourite::class);

        $product = $this->favourites->add(
            $request->user(),
            (int) $request->validated('product_id'),
        );

        return $this->respondWithResource(
            new ProductResource($product),
            'Product added to favourites.',
            201,
        );
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('viewAny', ProductFavourite::class);

        $this->favourites->remove($request->user(), $product);

        return $this->respondWithData(null, 'Product removed from favourites.');
    }
}
