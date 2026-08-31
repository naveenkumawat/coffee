<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Support\Collection;

interface ProductReadinessServiceInterface
{
    public function evaluate(Product $product): ProductReadinessReport;

    /**
     * @return Collection<int, ProductReadinessReport>
     */
    public function evaluateMany(Collection $products): Collection;

    /**
     * Throw when an active product is not launch-ready for public sellability.
     */
    public function assertCanBeActive(Product $product): void;

    /**
     * @return list<array{product: Product, report: ProductReadinessReport}>
     */
    public function incompleteProducts(): array;

    /**
     * @return array{total: int, ready: int, incomplete: int, items: list<array{name: string, missing: list<string>}>}
     */
    public function catalogSummary(): array;
}
