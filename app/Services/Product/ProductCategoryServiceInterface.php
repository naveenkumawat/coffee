<?php

namespace App\Services\Product;

use App\Models\ProductCategory;
use App\Transfers\Product\ProductCategoryTransferInterface;

interface ProductCategoryServiceInterface
{
    public function store(ProductCategoryTransferInterface $data): ProductCategory;

    public function update(ProductCategory $productCategory, ProductCategoryTransferInterface $data): ProductCategory;

    public function delete(ProductCategory $productCategory): void;
}
