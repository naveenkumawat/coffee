<?php

namespace App\Services\Product;

use App\Models\ProductFlavour;
use App\Repositories\Product\ProductFlavourRepositoryInterface;
use App\Transfers\Product\ProductFlavourTransferInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductFlavourService implements ProductFlavourServiceInterface
{
    public function __construct(
        protected ProductFlavourRepositoryInterface $flavours,
        protected ProductCatalogServiceInterface $catalog,
    ) {}

    public function store(ProductFlavourTransferInterface $data): ProductFlavour
    {
        $flavour = DB::transaction(function () use ($data): ProductFlavour {
            $attributes = $data->toArray();
            $attributes['slug'] = $this->uniqueSlug((string) $data->getName());

            $flavour = $this->flavours->create($attributes);
            $this->flavours->syncCategories($flavour, $data->getProductCategoryIds());

            return $flavour->fresh(['categories', 'products']);
        });

        $this->catalog->flushPublicCache();

        return $flavour;
    }

    public function update(ProductFlavour $productFlavour, ProductFlavourTransferInterface $data): ProductFlavour
    {
        $productFlavour = DB::transaction(function () use ($productFlavour, $data): ProductFlavour {
            $attributes = $data->toArray();
            $attributes['slug'] = $this->uniqueSlug((string) $data->getName(), (int) $productFlavour->getKey());

            $productFlavour = $this->flavours->update($productFlavour, $attributes);
            $this->flavours->syncCategories($productFlavour, $data->getProductCategoryIds());

            return $productFlavour->fresh(['categories', 'products']);
        });

        $this->catalog->flushPublicCache();

        return $productFlavour;
    }

    public function delete(ProductFlavour $productFlavour): void
    {
        if ($productFlavour->products()->exists()) {
            throw ValidationException::withMessages([
                'flavour' => 'This flavour cannot be archived while products still use it.',
            ]);
        }

        DB::transaction(function () use ($productFlavour): void {
            $productFlavour->forceFill(['is_active' => false])->save();
            $productFlavour->categories()->detach();
            $this->flavours->delete($productFlavour);
        });

        $this->catalog->flushPublicCache();
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->flavours->slugExists($slug, $ignoreId)) {
            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            $suffix++;
        }

        return $slug;
    }
}
