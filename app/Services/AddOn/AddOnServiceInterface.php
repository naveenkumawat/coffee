<?php

namespace App\Services\AddOn;

use App\Models\AddOn;
use App\Models\Product;
use App\Models\ProductAddOn;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

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

    public function syncImage(AddOn $addOn, ?UploadedFile $image, bool $remove): AddOn;

    public function toggleActive(AddOn $addOn): AddOn;

    /**
     * @param  list<array<string, mixed>>  $assignments
     */
    public function syncProductAssignments(Product $product, array $assignments): void;

    /**
     * @return Collection<int, mixed>
     */
    public function resolveRecipeLinesForConsumption(Product $product, ?ProductVariant $variant, AddOn $addOn): Collection;

    /**
     * @return array{cost: string, selling_price: string, margin: string}
     */
    public function calculateAssignmentEconomics(ProductAddOn $assignment, ?ProductVariant $variant = null): array;

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
