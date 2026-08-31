<?php

namespace App\Services\Product;

use App\Models\ProductTag;

interface ProductTagServiceInterface
{
    public function store(array $data): ProductTag;

    public function update(ProductTag $tag, array $data): ProductTag;

    public function delete(ProductTag $tag): void;
}
