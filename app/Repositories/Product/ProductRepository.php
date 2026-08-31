<?php

namespace App\Repositories\Product;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\ProductVariant;
use App\Repositories\AbstractRepository;
use App\Transfers\Product\ProductFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository extends AbstractRepository implements ProductRepositoryInterface
{
    public function __construct(
        protected Product $model,
        protected ProductVariant $variantModel,
    ) {}

    public function paginateForAdmin(ProductFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForBarista(ProductFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->where('is_active', true)
            ->with(['category', 'flavours', 'defaultVariant'])
            ->orderByDesc('is_available')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForCategory(ProductCategory $productCategory, int $perPage = 12): LengthAwarePaginator
    {
        return $productCategory->products()
            ->with(['defaultVariant', 'flavours'])
            ->orderByDesc('is_available')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function paginateForFlavour(ProductFlavour $productFlavour, int $perPage = 12): LengthAwarePaginator
    {
        return $productFlavour->products()
            ->with(['category', 'defaultVariant'])
            ->orderByDesc('is_available')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function publicCatalog(): Collection
    {
        return ProductCategory::query()
            ->where('is_active', true)
            ->with([
                'products' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->with(['defaultVariant', 'flavours'])
                    ->orderByDesc('is_featured')
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (ProductCategory $category): bool => $category->products->isNotEmpty())
            ->values();
    }

    public function featured(int $limit = 4): Collection
    {
        return $this->model->newQuery()
            ->with([
                'category',
                'defaultVariant',
                'flavours',
                'tags' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
            ])
            ->where('is_active', true)
            ->where('is_available', true)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function paginatePublic(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with([
                'category',
                'flavours',
                'tags' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                'defaultVariant.recipe.lines' => fn ($query) => $query
                    ->where('show_to_customer', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'defaultVariant.recipe.lines.ingredient',
                'variants' => fn ($query) => $query->where('is_active', true)->where('is_available', true),
                'variants.recipe.lines' => fn ($query) => $query
                    ->where('show_to_customer', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'variants.recipe.lines.ingredient',
            ])
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = trim((string) $filters['search']);

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('customer_ingredient_summary', 'like', "%{$search}%");
                });
            })
            ->when(filled($filters['product_category_id'] ?? null), fn ($query) => $query->where('product_category_id', (int) $filters['product_category_id']))
            ->when(filled($filters['product_flavour_id'] ?? null), fn ($query) => $query->whereHas('flavours', fn ($flavourQuery) => $flavourQuery->whereKey((int) $filters['product_flavour_id'])->where('is_active', true)))
            ->when(($filters['featured'] ?? null) === 'featured', fn ($query) => $query->where('is_featured', true))
            ->when(($filters['new'] ?? null) === 'new', fn ($query) => $query->where('is_new', true))
            ->when(($filters['bestseller'] ?? null) === 'bestseller', fn ($query) => $query->where('is_bestseller', true))
            ->orderByDesc('is_featured')
            ->orderByDesc('is_bestseller')
            ->orderByDesc('is_new')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginatePublicVariants(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->variantModel->newQuery()
            ->with(['product.category'])
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('product', function ($query) use ($filters): void {
                $query
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('is_active', true))
                    ->when(filled($filters['product_id'] ?? null), fn ($productQuery) => $productQuery->whereKey((int) $filters['product_id']))
                    ->when(filled($filters['product_category_id'] ?? null), fn ($productQuery) => $productQuery->where('product_category_id', (int) $filters['product_category_id']))
                    ->when(filled($filters['product_flavour_id'] ?? null), fn ($productQuery) => $productQuery->whereHas('flavours', fn ($flavourQuery) => $flavourQuery->whereKey((int) $filters['product_flavour_id'])->where('is_active', true)))
                    ->when(filled($filters['search'] ?? null), function ($productQuery) use ($filters): void {
                        $search = trim((string) $filters['search']);

                        $productQuery->where(function ($nestedQuery) use ($search): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('short_description', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        });
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublicById(int $productId): ?Product
    {
        return $this->model->newQuery()
            ->with([
                'category',
                'flavours',
                'tags' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                'defaultVariant.recipe.lines' => fn ($query) => $query
                    ->where('show_to_customer', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'defaultVariant.recipe.lines.ingredient',
                'variants' => fn ($query) => $query->where('is_active', true)->where('is_available', true),
                'variants.recipe.lines' => fn ($query) => $query
                    ->where('show_to_customer', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'variants.recipe.lines.ingredient',
            ])
            ->whereKey($productId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->first();
    }

    public function create(array $attributes): Product
    {
        /** @var Product $product */
        $product = $this->persist($this->model->newInstance(), $attributes);

        return $product;
    }

    public function update(Product $product, array $attributes): Product
    {
        /** @var Product $product */
        $product = $this->persist($product, $attributes);

        return $product;
    }

    public function delete(Product $product): void
    {
        $this->remove($product);
    }

    public function syncFlavours(Product $product, array $flavourIds): void
    {
        $product->flavours()->sync($flavourIds);
    }

    public function syncTags(Product $product, array $tagIds): void
    {
        $product->tags()->sync($tagIds);
    }

    public function replaceVariants(Product $product, array $variants): Product
    {
        $existingVariants = $product->variants()->withTrashed()->get()->keyBy('id');
        $keptVariantIds = [];

        foreach ($variants as $index => $variant) {
            $variantModel = filled($variant['id'] ?? null)
                ? $existingVariants->get((int) $variant['id'])
                : null;

            $attributes = [
                'name' => $variant['name'],
                'serving_size_value' => $variant['serving_size_value'],
                'serving_size_unit' => $variant['serving_size_unit'],
                'price' => $variant['price'],
                'sort_order' => $variant['sort_order'] ?: ($index + 1),
                'is_active' => (bool) ($variant['is_active'] ?? true),
                'is_available' => (bool) ($variant['is_available'] ?? true),
                'deleted_at' => null,
            ];

            if ($variantModel && (int) $variantModel->product_id === (int) $product->getKey()) {
                $variantModel->fill($attributes)->save();
                $keptVariantIds[] = (int) $variantModel->getKey();

                continue;
            }

            $createdVariant = $product->variants()->create($attributes);
            $keptVariantIds[] = (int) $createdVariant->getKey();
        }

        $product->variants()
            ->whereNotIn('id', $keptVariantIds)
            ->get()
            ->each(function ($variant): void {
                $variant->forceFill([
                    'is_active' => false,
                    'is_available' => false,
                ])->save();
                $variant->delete();
            });

        return $product->fresh(['category', 'flavours', 'variants', 'defaultVariant']);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function skuExists(?string $sku, ?int $ignoreId = null): bool
    {
        if (! filled($sku)) {
            return false;
        }

        return $this->model->newQuery()
            ->withTrashed()
            ->where('sku', $sku)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    protected function filteredQuery(ProductFilterTransferInterface $filters)
    {
        return $this->model->newQuery()
            ->with(['category', 'flavours', 'defaultVariant'])
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('flavours', fn ($flavourQuery) => $flavourQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters->getProductCategoryId(), fn ($query) => $query->where('product_category_id', $filters->getProductCategoryId()))
            ->when($filters->getProductFlavourId(), fn ($query) => $query->whereHas('flavours', fn ($flavourQuery) => $flavourQuery->whereKey($filters->getProductFlavourId())))
            ->when($filters->getStatus() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters->getStatus() === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters->getAvailability() === 'available', fn ($query) => $query->where('is_available', true))
            ->when($filters->getAvailability() === 'unavailable', fn ($query) => $query->where('is_available', false))
            ->when($filters->getFeatured() === 'featured', fn ($query) => $query->where('is_featured', true))
            ->when($filters->getFeatured() === 'standard', fn ($query) => $query->where('is_featured', false));
    }
}
