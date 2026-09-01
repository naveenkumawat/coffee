<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PromotionCatalogServiceInterface
{
    public function paginateForAdmin(int $perPage = 30): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Promotion;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Promotion $promotion, array $data): Promotion;

    public function delete(Promotion $promotion): void;

    public function setActive(Promotion $promotion, bool $isActive): Promotion;

    public function duplicate(Promotion $promotion): Promotion;
}
