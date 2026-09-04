<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyRewardStatus;
use App\Models\LoyaltyReward;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LoyaltyRewardCatalogServiceInterface
{
    public function paginateForAdmin(int $perPage = 30): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): LoyaltyReward;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LoyaltyReward $reward, array $data): LoyaltyReward;

    public function setStatus(LoyaltyReward $reward, LoyaltyRewardStatus $status): LoyaltyReward;

    public function archive(LoyaltyReward $reward): void;
}
