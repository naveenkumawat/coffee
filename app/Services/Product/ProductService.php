<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Repositories\Product\ProductFlavourRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Support\PublicMedia;
use App\Transfers\Product\ProductTransferInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService implements ProductServiceInterface
{
    public function __construct(
        protected ProductRepositoryInterface $products,
        protected ProductFlavourRepositoryInterface $flavours,
        protected ProductCatalogServiceInterface $catalog,
    ) {}

    public function store(ProductTransferInterface $data): Product
    {
        $product = DB::transaction(function () use ($data): Product {
            $attributes = $this->prepareAttributes($data);
            $product = $this->products->create($attributes);
            $this->products->syncFlavours($product, $data->getProductFlavourIds());

            return $this->products->replaceVariants($product, $this->prepareVariants($data->getVariants()));
        });

        $this->catalog->flushPublicCache();

        return $product;
    }

    public function update(Product $product, ProductTransferInterface $data): Product
    {
        $product = DB::transaction(function () use ($product, $data): Product {
            $attributes = $this->prepareAttributes($data, (int) $product->getKey());
            $product = $this->products->update($product, $attributes);
            $this->products->syncFlavours($product, $data->getProductFlavourIds());

            return $this->products->replaceVariants($product, $this->prepareVariants($data->getVariants()));
        });

        $this->catalog->flushPublicCache();

        return $product;
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->variants()->get()->each(function ($variant): void {
                $variant->forceFill([
                    'is_active' => false,
                    'is_available' => false,
                ])->save();
                $variant->delete();
            });

            $product->forceFill([
                'is_active' => false,
                'is_available' => false,
                'is_featured' => false,
                'is_new' => false,
                'is_bestseller' => false,
            ])->save();

            $this->products->delete($product);
        });

        $this->catalog->flushPublicCache();
    }

    public function syncImage(Product $product, ?UploadedFile $image, bool $remove): Product
    {
        $previous = $product->image_path;

        if ($image !== null) {
            $path = PublicMedia::store($image, PublicMedia::DIRECTORY_PRODUCTS);
            $product = $this->products->update($product, ['image_path' => $path]);
            PublicMedia::deleteManaged($previous);
            $this->catalog->flushPublicCache();

            return $product;
        }

        if ($remove) {
            $product = $this->products->update($product, ['image_path' => null]);
            PublicMedia::deleteManaged($previous);
            $this->catalog->flushPublicCache();
        }

        return $product;
    }

    protected function prepareAttributes(ProductTransferInterface $data, ?int $ignoreId = null): array
    {
        $sku = filled($data->getSku()) ? Str::upper(trim((string) $data->getSku())) : null;

        if ($this->products->skuExists($sku, $ignoreId)) {
            throw ValidationException::withMessages([
                'sku' => 'This SKU is already assigned to another product.',
            ]);
        }

        return array_merge($data->toArray(), [
            'slug' => $this->uniqueSlug((string) $data->getName(), $ignoreId),
            'sku' => $sku,
        ]);
    }

    protected function prepareVariants(array $variants): array
    {
        return collect($variants)->map(function (array $variant, int $index): array {
            return [
                'id' => $variant['id'] ?? null,
                'name' => trim((string) $variant['name']),
                'serving_size_value' => bcdiv((string) $variant['serving_size_value'], '1', 3),
                'serving_size_unit' => (string) $variant['serving_size_unit'],
                'price' => bcdiv((string) $variant['price'], '1', 2),
                'sort_order' => (int) ($variant['sort_order'] ?: ($index + 1)),
                'is_active' => (bool) ($variant['is_active'] ?? true),
                'is_available' => (bool) ($variant['is_available'] ?? true),
            ];
        })->all();
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->products->slugExists($slug, $ignoreId)) {
            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            $suffix++;
        }

        return $slug;
    }
}
