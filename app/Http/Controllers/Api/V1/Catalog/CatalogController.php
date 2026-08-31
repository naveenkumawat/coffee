<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductIndexRequest;
use App\Http\Resources\Api\V1\ProductCategoryResource;
use App\Http\Resources\Api\V1\ProductFlavourResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\ProductVariantResource;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Product\ProductFlavourRepositoryInterface;
use App\Services\Product\ProductCatalogServiceInterface;
use App\Services\Rating\ProductRatingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CatalogController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected ProductCatalogServiceInterface $catalog,
        protected ProductCategoryRepositoryInterface $categories,
        protected ProductFlavourRepositoryInterface $flavours,
        protected ProductRatingServiceInterface $ratings,
    ) {}

    public function categories(): JsonResponse
    {
        return $this->respondWithCollection(
            $this->categories->publicCatalog(),
            ProductCategoryResource::class,
            'Product categories retrieved.',
        );
    }

    public function flavours(): JsonResponse
    {
        return $this->respondWithCollection(
            $this->flavours->publicCatalog(),
            ProductFlavourResource::class,
            'Product flavours retrieved.',
        );
    }

    public function products(ProductIndexRequest $request): JsonResponse|Response
    {
        $filters = $request->catalogFilters();
        $payload = $this->catalog->listPublicProductPayload($filters);

        return $this->respondWithPublicCatalogue(
            $request,
            $payload,
            'Products retrieved.',
            filtersEmpty: $filters === [],
        );
    }

    public function featured(Request $request): JsonResponse|Response
    {
        $payload = $this->catalog->listPublicProductPayload(['featured' => 'featured']);

        return $this->respondWithPublicCatalogue(
            $request,
            $payload,
            'Featured products retrieved.',
            filtersEmpty: false,
        );
    }

    public function show(Request $request, int $product): JsonResponse
    {
        $productModel = $this->catalog->findPublicProduct($product);

        abort_if($productModel === null, 404);

        $customer = $request->user('web') ?? $request->user();
        $payload = $this->ratings->detailPayload($customer, $productModel);

        $productModel->setRelation('myRating', $payload['my_rating']);
        $productModel->setAttribute('can_rate', $payload['can_rate']);

        return $this->respondWithResource(
            new ProductResource($productModel),
            'Product detail retrieved.',
        );
    }

    public function variants(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'product_flavour_id' => ['nullable', 'integer', 'exists:product_flavours,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        return $this->respondWithPaginator(
            $this->catalog->paginatePublicVariants($validated, $perPage),
            ProductVariantResource::class,
            'Product variants retrieved.',
        );
    }

    /**
     * @param  array<int, mixed>  $payload
     */
    protected function respondWithPublicCatalogue(
        Request $request,
        array $payload,
        string $message,
        bool $filtersEmpty,
    ): JsonResponse|Response {
        $etagSeed = $this->catalog->publicCatalogVersion().'|'.hash('sha256', json_encode($payload) ?: '');
        $etag = '"'.$etagSeed.'"';
        $lastModified = $this->catalog->publicCatalogUpdatedAt();

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->noContent(SymfonyResponse::HTTP_NOT_MODIFIED)->withHeaders([
                'ETag' => $etag,
                'Last-Modified' => $lastModified->toRfc7231String(),
                'Cache-Control' => $this->publicCatalogueCacheControl($filtersEmpty),
                'Vary' => 'Accept, Authorization',
            ]);
        }

        if ($request->headers->has('If-Modified-Since')) {
            $since = strtotime((string) $request->headers->get('If-Modified-Since'));

            if ($since !== false && $lastModified->getTimestamp() <= $since) {
                return response()->noContent(SymfonyResponse::HTTP_NOT_MODIFIED)->withHeaders([
                    'ETag' => $etag,
                    'Last-Modified' => $lastModified->toRfc7231String(),
                    'Cache-Control' => $this->publicCatalogueCacheControl($filtersEmpty),
                    'Vary' => 'Accept, Authorization',
                ]);
            }
        }

        return $this->respondWithData($payload, $message)->withHeaders([
            'ETag' => $etag,
            'Last-Modified' => $lastModified->toRfc7231String(),
            'Cache-Control' => $this->publicCatalogueCacheControl($filtersEmpty),
            'Vary' => 'Accept, Authorization',
        ]);
    }

    protected function publicCatalogueCacheControl(bool $filtersEmpty): string
    {
        // Public menu list is anonymous; short max-age + revalidation keeps admin updates fresh.
        if ($filtersEmpty) {
            return 'public, max-age=60, must-revalidate';
        }

        return 'public, max-age=30, must-revalidate';
    }
}
