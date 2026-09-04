<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyRewardStatus;
use App\Enums\LoyaltyRewardType;
use App\Models\LoyaltyReward;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyRewardCatalogService implements LoyaltyRewardCatalogServiceInterface
{
    public function paginateForAdmin(int $perPage = 30, array $filters = []): LengthAwarePaginator
    {
        $status = trim((string) ($filters['status'] ?? ''));

        return LoyaltyReward::query()
            ->withCount('orders')
            ->when(
                $status !== '' && $status !== 'all',
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                ($filters['include_archived'] ?? false) === true,
                fn ($query) => $query->withTrashed(),
            )
            ->orderByDesc('priority')
            ->orderBy('points_cost')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function store(array $data): LoyaltyReward
    {
        return DB::transaction(function () use ($data): LoyaltyReward {
            $reward = LoyaltyReward::query()->create($this->prepareAttributes($data));
            $this->syncRelations($reward, $data);

            return $reward->fresh(['products', 'productCategories', 'addOns']) ?? $reward;
        });
    }

    public function update(LoyaltyReward $reward, array $data): LoyaltyReward
    {
        return DB::transaction(function () use ($reward, $data): LoyaltyReward {
            $reward->fill($this->prepareAttributes($data));
            $reward->save();
            $this->syncRelations($reward, $data);

            return $reward->fresh(['products', 'productCategories', 'addOns']) ?? $reward;
        });
    }

    public function setStatus(LoyaltyReward $reward, LoyaltyRewardStatus $status): LoyaltyReward
    {
        $reward->forceFill(['status' => $status])->save();

        return $reward->fresh() ?? $reward;
    }

    public function archive(LoyaltyReward $reward): void
    {
        DB::transaction(function () use ($reward): void {
            $reward->forceFill(['status' => LoyaltyRewardStatus::Archived])->save();
            $reward->delete();
        });
    }

    public function duplicate(LoyaltyReward $reward): LoyaltyReward
    {
        return DB::transaction(function () use ($reward): LoyaltyReward {
            $reward->loadMissing(['products', 'productCategories', 'addOns']);

            $copy = $reward->replicate(['deleted_at']);
            $copy->name = trim((string) $reward->name).' (Copy)';
            $copy->status = LoyaltyRewardStatus::Paused;
            $copy->save();

            $copy->products()->sync($reward->products->modelKeys());
            $copy->productCategories()->sync($reward->productCategories->modelKeys());
            $copy->addOns()->sync($reward->addOns->modelKeys());

            return $copy->fresh(['products', 'productCategories', 'addOns']) ?? $copy;
        });
    }

    /**
     * @param  list<int>  $rewardIds
     * @return array{updated: int, failed: list<array{id: int, reason: string}>}
     */
    public function bulkSetStatus(array $rewardIds, LoyaltyRewardStatus $status): array
    {
        $ids = collect($rewardIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            throw ValidationException::withMessages([
                'reward_ids' => 'Select at least one loyalty reward.',
            ]);
        }

        if (! in_array($status, [LoyaltyRewardStatus::Active, LoyaltyRewardStatus::Paused], true)) {
            throw ValidationException::withMessages([
                'status' => 'Bulk status updates only support activate or pause.',
            ]);
        }

        $updated = 0;
        $failed = [];

        foreach ($ids as $id) {
            $reward = LoyaltyReward::query()->find($id);

            if ($reward === null) {
                $failed[] = ['id' => $id, 'reason' => 'not_found'];

                continue;
            }

            if ($reward->status === LoyaltyRewardStatus::Archived) {
                $failed[] = ['id' => $id, 'reason' => 'archived'];

                continue;
            }

            $this->setStatus($reward, $status);
            $updated++;
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data): array
    {
        $type = LoyaltyRewardType::from((string) ($data['reward_type'] ?? LoyaltyRewardType::FixedOrderDiscount->value));
        $status = LoyaltyRewardStatus::from((string) ($data['status'] ?? LoyaltyRewardStatus::Active->value));

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'status' => $status,
            'reward_type' => $type,
            'points_cost' => max(1, (int) ($data['points_cost'] ?? 1)),
            'config' => $this->buildConfig($type, $data),
            'minimum_spend' => filled($data['minimum_spend'] ?? null) ? $data['minimum_spend'] : null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => filled($data['usage_limit'] ?? null) ? (int) $data['usage_limit'] : null,
            'usage_limit_per_customer' => filled($data['usage_limit_per_customer'] ?? null)
                ? (int) $data['usage_limit_per_customer']
                : null,
            'usage_limit_per_customer_period_days' => filled($data['usage_limit_per_customer_period_days'] ?? null)
                ? (int) $data['usage_limit_per_customer_period_days']
                : null,
            'priority' => (int) ($data['priority'] ?? 0),
            'customer_description' => filled($data['customer_description'] ?? null)
                ? trim((string) $data['customer_description'])
                : null,
            'internal_note' => filled($data['internal_note'] ?? null)
                ? trim((string) $data['internal_note'])
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildConfig(LoyaltyRewardType $type, array $data): array
    {
        return match ($type) {
            LoyaltyRewardType::FixedOrderDiscount => [
                'discount_amount' => number_format((float) ($data['discount_amount'] ?? 0), 2, '.', ''),
            ],
            LoyaltyRewardType::PercentageOrderDiscount => array_filter([
                'percent' => number_format((float) ($data['percent'] ?? 0), 4, '.', ''),
                'maximum_discount_amount' => filled($data['maximum_discount_amount'] ?? null)
                    ? number_format((float) $data['maximum_discount_amount'], 2, '.', '')
                    : null,
            ], fn ($value): bool => $value !== null && $value !== ''),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncRelations(LoyaltyReward $reward, array $data): void
    {
        $productIds = collect($data['product_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $categoryIds = collect($data['product_category_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $addOnIds = collect($data['add_on_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $type = $reward->reward_type instanceof LoyaltyRewardType
            ? $reward->reward_type
            : LoyaltyRewardType::tryFrom((string) $reward->reward_type);

        if ($type === LoyaltyRewardType::FreeAddOn && $addOnIds === []) {
            throw ValidationException::withMessages([
                'add_on_ids' => 'Select at least one add-on for this reward type.',
            ]);
        }

        if (in_array($type, [LoyaltyRewardType::SpecificProductReward, LoyaltyRewardType::FreeBaseProduct], true)
            && $productIds === []) {
            throw ValidationException::withMessages([
                'product_ids' => 'Select at least one product for this reward type.',
            ]);
        }

        if ($type === LoyaltyRewardType::CategoryProductReward && $categoryIds === [] && $productIds === []) {
            throw ValidationException::withMessages([
                'product_category_ids' => 'Select at least one category or product for this reward type.',
            ]);
        }

        $reward->products()->sync($productIds);
        $reward->productCategories()->sync($categoryIds);
        $reward->addOns()->sync($addOnIds);
    }
}
