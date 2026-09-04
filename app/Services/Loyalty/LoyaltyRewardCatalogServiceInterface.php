<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyRewardStatus;
use App\Models\LoyaltyReward;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LoyaltyRewardCatalogServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(int $perPage = 30, array $filters = []): LengthAwarePaginator;

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

    public function duplicate(LoyaltyReward $reward): LoyaltyReward;

    /**
     * @param  list<int>  $rewardIds
     * @return array{updated: int, failed: list<array{id: int, reason: string}>}
     */
    public function bulkSetStatus(array $rewardIds, LoyaltyRewardStatus $status): array;
}
