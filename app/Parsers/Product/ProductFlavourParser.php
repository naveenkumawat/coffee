<?php

namespace App\Parsers\Product;

use App\Models\ProductFlavour;
use App\Parsers\AbstractParser;
use App\Transfers\Product\ProductFlavourFilterTransferInterface;
use App\Transfers\Product\ProductFlavourTransferInterface;

class ProductFlavourParser extends AbstractParser implements ProductFlavourParserInterface
{
    public function getTransferFromModelEntity(ProductFlavour $productFlavour): ProductFlavourTransferInterface
    {
        $transfer = $this->make(ProductFlavourTransferInterface::class);
        $transfer->setId($productFlavour->getKey());
        $transfer->setName($productFlavour->name);
        $transfer->setDescription($productFlavour->description);
        $transfer->setImagePath($productFlavour->image_path);
        $transfer->setProductCategoryIds($productFlavour->categories()->pluck('product_categories.id')->map(fn ($id): int => (int) $id)->all());
        $transfer->setIsActive((bool) $productFlavour->is_active);
        $transfer->setCreatedAt($productFlavour->created_at);
        $transfer->setUpdatedAt($productFlavour->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $productFlavourData): ProductFlavourTransferInterface
    {
        $transfer = $this->make(ProductFlavourTransferInterface::class);
        $transfer->setName(trim((string) $productFlavourData['name']));
        $transfer->setDescription(filled($productFlavourData['description'] ?? null) ? trim((string) $productFlavourData['description']) : null);
        $transfer->setImagePath(filled($productFlavourData['image_path'] ?? null) ? trim((string) $productFlavourData['image_path']) : null);
        $transfer->setProductCategoryIds(collect($productFlavourData['product_category_ids'] ?? [])->filter(fn ($id) => filled($id))->map(fn ($id): int => (int) $id)->unique()->values()->all());
        $transfer->setIsActive((bool) ($productFlavourData['is_active'] ?? true));

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): ProductFlavourFilterTransferInterface
    {
        $transfer = $this->make(ProductFlavourFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);
        $transfer->setProductCategoryId(filled($filterData['product_category_id'] ?? null) ? (int) $filterData['product_category_id'] : null);

        return $transfer;
    }
}
