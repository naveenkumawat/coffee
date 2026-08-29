<?php

namespace App\Parsers\Product;

use App\Models\ProductCategory;
use App\Transfers\Product\ProductCategoryFilterTransferInterface;
use App\Transfers\Product\ProductCategoryTransferInterface;

interface ProductCategoryParserInterface
{
    public function getTransferFromModelEntity(ProductCategory $productCategory): ProductCategoryTransferInterface;

    public function getTransferFromArrayData(array $productCategoryData): ProductCategoryTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): ProductCategoryFilterTransferInterface;
}
