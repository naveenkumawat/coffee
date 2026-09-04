<?php

namespace App\Services\Product;

use App\Models\ProductCategory;
use App\Transfers\Product\ProductCategoryTransferInterface;
use Illuminate\Http\UploadedFile;

interface ProductCategoryServiceInterface
{
    public function store(ProductCategoryTransferInterface $data): ProductCategory;

    public function update(ProductCategory $productCategory, ProductCategoryTransferInterface $data): ProductCategory;

    public function syncImage(ProductCategory $category, ?UploadedFile $image, bool $remove): ProductCategory;

    public function delete(ProductCategory $productCategory): void;
}
