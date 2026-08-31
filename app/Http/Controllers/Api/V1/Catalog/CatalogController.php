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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected ProductCatalogServiceInterface $catalog,
        protected ProductCategoryRepositoryInterface $categories,
        protected ProductFlavourRepositoryInterface $flavours,
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

    public function products(ProductIndexRequest $request): JsonResponse
    {
        $filters = $request->catalogFilters();
        $filters['availability'] = 'available';
        $perPage = max(1, min(50, (int) $request->integer('per_page', 12)));

        return $this->respondWithPaginator(
            $this->catalog->paginatePublicProducts($filters, $perPage),
            ProductResource::class,
            'Products retrieved.',
        );
    }

    public function featured(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->integer('per_page', 12)));

        return $this->respondWithPaginator(
            $this->catalog->paginatePublicProducts(['featured' => 'featured'], $perPage),
            ProductResource::class,
            'Featured products retrieved.',
        );
    }

    public function show(int $product): JsonResponse
    {
        $productModel = $this->catalog->findPublicProduct($product);

        abort_if($productModel === null, 404);

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
}
