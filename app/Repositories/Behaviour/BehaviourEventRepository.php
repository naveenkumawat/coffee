<?php

namespace App\Repositories\Behaviour;

use App\Models\CustomerBehaviourEvent;
use App\Models\CustomerVisitorIdentity;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BehaviourEventRepository implements BehaviourEventRepositoryInterface
{
    public function create(array $attributes): CustomerBehaviourEvent
    {
        return CustomerBehaviourEvent::query()->create($attributes);
    }

    public function findByIdempotencyKey(string $key): ?CustomerBehaviourEvent
    {
        return CustomerBehaviourEvent::query()
            ->where('idempotency_key', $key)
            ->first();
    }

    public function findVisitorIdentity(string $visitorKey): ?CustomerVisitorIdentity
    {
        return CustomerVisitorIdentity::query()
            ->where('visitor_key', $visitorKey)
            ->first();
    }

    public function claimVisitorIdentity(string $visitorKey, User $customer): CustomerVisitorIdentity
    {
        return CustomerVisitorIdentity::query()->create([
            'visitor_key' => $visitorKey,
            'customer_id' => $customer->getKey(),
            'claimed_at' => now(),
        ]);
    }

    public function attachUnclaimedVisitorEvents(string $visitorKey, User $customer): int
    {
        return CustomerBehaviourEvent::query()
            ->where('visitor_key', $visitorKey)
            ->whereNull('customer_id')
            ->update([
                'customer_id' => $customer->getKey(),
                'updated_at' => now(),
            ]);
    }

    public function pruneOlderThan(CarbonInterface $cutoff, int $chunkSize = 500): int
    {
        $deleted = 0;

        do {
            $ids = CustomerBehaviourEvent::query()
                ->where('occurred_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += CustomerBehaviourEvent::query()
                ->whereIn('id', $ids)
                ->delete();
        } while ($ids->count() === $chunkSize);

        return $deleted;
    }

    /**
     * @return array{events: int, visitors: int, oldest_occurred_at: ?string, newest_occurred_at: ?string}
     */
    public function diagnosticsSummary(): array
    {
        $bounds = CustomerBehaviourEvent::query()
            ->selectRaw('MIN(occurred_at) as oldest_occurred_at, MAX(occurred_at) as newest_occurred_at')
            ->first();

        return [
            'events' => (int) CustomerBehaviourEvent::query()->count(),
            'visitors' => (int) CustomerVisitorIdentity::query()->count(),
            'oldest_occurred_at' => $bounds?->oldest_occurred_at,
            'newest_occurred_at' => $bounds?->newest_occurred_at,
        ];
    }

    public function productExists(int $productId): bool
    {
        return DB::table('products')
            ->where('id', $productId)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function categoryExists(int $categoryId): bool
    {
        return DB::table('product_categories')
            ->where('id', $categoryId)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function variantExists(int $variantId): bool
    {
        return DB::table('product_variants')
            ->where('id', $variantId)
            ->whereNull('deleted_at')
            ->exists();
    }
}
