<?php

namespace App\Parsers\Product;

use App\Models\ProductCategory;
use App\Parsers\AbstractParser;
use App\Transfers\Product\ProductCategoryFilterTransferInterface;
use App\Transfers\Product\ProductCategoryTransferInterface;

class ProductCategoryParser extends AbstractParser implements ProductCategoryParserInterface
{
    public function getTransferFromModelEntity(ProductCategory $productCategory): ProductCategoryTransferInterface
    {
        $transfer = $this->make(ProductCategoryTransferInterface::class);
        $transfer->setId($productCategory->getKey());
        $transfer->setName($productCategory->name);
        $transfer->setDescription($productCategory->description);
        $transfer->setImagePath($productCategory->image_path);
        $transfer->setSortOrder((int) $productCategory->sort_order);
        $transfer->setIsActive((bool) $productCategory->is_active);
        $transfer->setCreatedAt($productCategory->created_at);
        $transfer->setUpdatedAt($productCategory->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $productCategoryData): ProductCategoryTransferInterface
    {
        $transfer = $this->make(ProductCategoryTransferInterface::class);
        $transfer->setName(trim((string) $productCategoryData['name']));
        $transfer->setDescription(filled($productCategoryData['description'] ?? null) ? trim((string) $productCategoryData['description']) : null);
        $transfer->setImagePath(filled($productCategoryData['image_path'] ?? null) ? trim((string) $productCategoryData['image_path']) : null);
        $transfer->setSortOrder((int) ($productCategoryData['sort_order'] ?? 0));
        $transfer->setIsActive((bool) ($productCategoryData['is_active'] ?? true));

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): ProductCategoryFilterTransferInterface
    {
        $transfer = $this->make(ProductCategoryFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);

        return $transfer;
    }
}
