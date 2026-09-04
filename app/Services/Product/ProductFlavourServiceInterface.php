<?php

namespace App\Services\Product;

use App\Models\ProductFlavour;
use App\Transfers\Product\ProductFlavourTransferInterface;
use Illuminate\Http\UploadedFile;

interface ProductFlavourServiceInterface
{
    public function store(ProductFlavourTransferInterface $data): ProductFlavour;

    public function update(ProductFlavour $productFlavour, ProductFlavourTransferInterface $data): ProductFlavour;

    public function syncImage(ProductFlavour $flavour, ?UploadedFile $image, bool $remove): ProductFlavour;

    public function delete(ProductFlavour $productFlavour): void;
}
