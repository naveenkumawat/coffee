<?php

namespace App\Services\Product;

use App\Models\ProductCategory;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Transfers\Product\ProductCategoryTransferInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCategoryService implements ProductCategoryServiceInterface
{
    public function __construct(
        protected ProductCategoryRepositoryInterface $categories,
        protected ProductCatalogServiceInterface $catalog,
    ) {}

    public function store(ProductCategoryTransferInterface $data): ProductCategory
    {
        $category = DB::transaction(function () use ($data): ProductCategory {
            $attributes = $data->toArray();
            $attributes['slug'] = $this->uniqueSlug((string) $data->getName());

            return $this->categories->create($attributes);
        });

        $this->catalog->flushPublicCache();

        return $category;
    }

    public function update(ProductCategory $productCategory, ProductCategoryTransferInterface $data): ProductCategory
    {
        $productCategory = DB::transaction(function () use ($productCategory, $data): ProductCategory {
            $attributes = $data->toArray();
            $attributes['slug'] = $this->uniqueSlug((string) $data->getName(), (int) $productCategory->getKey());

            return $this->categories->update($productCategory, $attributes);
        });

        $this->catalog->flushPublicCache();

        return $productCategory;
    }

    public function delete(ProductCategory $productCategory): void
    {
        if ($productCategory->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This product category cannot be archived while products still belong to it.',
            ]);
        }

        DB::transaction(function () use ($productCategory): void {
            $productCategory->forceFill(['is_active' => false])->save();
            $this->categories->delete($productCategory);
        });

        $this->catalog->flushPublicCache();
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->categories->slugExists($slug, $ignoreId)) {
            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            $suffix++;
        }

        return $slug;
    }
}
