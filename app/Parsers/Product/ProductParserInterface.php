<?php

namespace App\Parsers\Product;

use App\Models\Product;
use App\Transfers\Product\ProductFilterTransferInterface;
use App\Transfers\Product\ProductTransferInterface;

interface ProductParserInterface
{
    public function getTransferFromModelEntity(Product $product): ProductTransferInterface;

    public function getTransferFromArrayData(array $productData): ProductTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): ProductFilterTransferInterface;
}
