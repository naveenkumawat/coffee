<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Transfers\Product\ProductTransferInterface;

interface ProductServiceInterface
{
    public function store(ProductTransferInterface $data): Product;

    public function update(Product $product, ProductTransferInterface $data): Product;

    public function delete(Product $product): void;
}
