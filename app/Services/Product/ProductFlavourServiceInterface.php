<?php

namespace App\Services\Product;

use App\Models\ProductFlavour;
use App\Transfers\Product\ProductFlavourTransferInterface;

interface ProductFlavourServiceInterface
{
    public function store(ProductFlavourTransferInterface $data): ProductFlavour;

    public function update(ProductFlavour $productFlavour, ProductFlavourTransferInterface $data): ProductFlavour;

    public function delete(ProductFlavour $productFlavour): void;
}
