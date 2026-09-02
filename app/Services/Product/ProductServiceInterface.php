<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Transfers\Product\ProductTransferInterface;
use Illuminate\Http\UploadedFile;

interface ProductServiceInterface
{
    public function store(ProductTransferInterface $data): Product;

    public function update(Product $product, ProductTransferInterface $data): Product;

    public function delete(Product $product): void;

    public function syncImage(Product $product, ?UploadedFile $image, bool $remove): Product;

    public function assertActiveProductIsLaunchReady(Product $product): void;

    /**
     * @param  list<array{add_on_id: int, price_override?: ?string, max_quantity?: ?int, sort_order?: int}>|null  $assignments
     */
    public function syncAddOnAssignments(Product $product, ?array $assignments): Product;
}
