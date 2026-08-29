<?php

namespace App\Parsers\Product;

use App\Models\ProductFlavour;
use App\Transfers\Product\ProductFlavourFilterTransferInterface;
use App\Transfers\Product\ProductFlavourTransferInterface;

interface ProductFlavourParserInterface
{
    public function getTransferFromModelEntity(ProductFlavour $productFlavour): ProductFlavourTransferInterface;

    public function getTransferFromArrayData(array $productFlavourData): ProductFlavourTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): ProductFlavourFilterTransferInterface;
}
