<?php

namespace App\Services\AddOn;

use App\Models\AddOn;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AddOnServiceInterface
{
    public function paginateForAdmin(?string $search = null): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): AddOn;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AddOn $addOn, array $data): AddOn;

    public function toggleActive(AddOn $addOn): AddOn;

    /**
     * @param  list<array{add_on_id: int, price_override?: ?string, max_quantity?: ?int, sort_order?: int}>  $assignments
     */
    public function syncProductAssignments(Product $product, array $assignments): void;

    /**
     * @return list<array{id: int, name: string, description: ?string, price: string, max_quantity: int}>
     */
    public function catalogAddOnsForProduct(Product $product): array;

    /**
     * @param  list<array{add_on_id: int, quantity: int}>  $selected
     * @return list<array{add_on_id: int, name: string, quantity: int, unit_price: string, line_total: string}>
     */
    public function resolveSelectionForProduct(Product $product, array $selected): array;

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function syncRecipeLines(AddOn $addOn, array $lines): void;
}
