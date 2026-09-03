<?php

namespace App\Repositories\Personalisation;

use App\Enums\BehaviourEventType;
use App\Enums\OrderStatus;
use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\PersonalisationProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PersonalisationProfileRepository implements PersonalisationProfileRepositoryInterface
{
    public function findForCustomer(int $customerId): ?PersonalisationProfile
    {
        return PersonalisationProfile::query()
            ->where('customer_id', $customerId)
            ->first();
    }

    public function findForVisitor(string $visitorKey): ?PersonalisationProfile
    {
        return PersonalisationProfile::query()
            ->where('visitor_key', $visitorKey)
            ->whereNull('customer_id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertCustomerProfile(int $customerId, array $attributes): PersonalisationProfile
    {
        $profile = $this->findForCustomer($customerId) ?? new PersonalisationProfile([
            'customer_id' => $customerId,
            'visitor_key' => null,
        ]);

        $profile->fill($attributes);
        $profile->customer_id = $customerId;
        $profile->visitor_key = null;
        $profile->save();

        return $profile->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertVisitorProfile(string $visitorKey, array $attributes): PersonalisationProfile
    {
        $profile = $this->findForVisitor($visitorKey) ?? new PersonalisationProfile([
            'customer_id' => null,
            'visitor_key' => $visitorKey,
        ]);

        $profile->fill($attributes);
        $profile->customer_id = null;
        $profile->visitor_key = $visitorKey;
        $profile->save();

        return $profile->fresh();
    }

    public function deleteForCustomer(int $customerId): bool
    {
        return PersonalisationProfile::query()
            ->where('customer_id', $customerId)
            ->delete() > 0;
    }

    public function deleteForVisitor(string $visitorKey): bool
    {
        return PersonalisationProfile::query()
            ->where('visitor_key', $visitorKey)
            ->whereNull('customer_id')
            ->delete() > 0;
    }

    /**
     * @return Collection<int, CustomerBehaviourEvent>
     */
    public function behaviourEventsForCustomer(int $customerId, CarbonInterface $since): Collection
    {
        return CustomerBehaviourEvent::query()
            ->where('customer_id', $customerId)
            ->where('occurred_at', '>=', $since)
            ->where('event_type', '!=', BehaviourEventType::OrderCompleted->value)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get([
                'id',
                'event_type',
                'product_id',
                'product_category_id',
                'product_variant_id',
                'metadata',
                'occurred_at',
            ]);
    }

    /**
     * @return Collection<int, CustomerBehaviourEvent>
     */
    public function behaviourEventsForVisitor(string $visitorKey, CarbonInterface $since): Collection
    {
        return CustomerBehaviourEvent::query()
            ->where('visitor_key', $visitorKey)
            ->whereNull('customer_id')
            ->where('occurred_at', '>=', $since)
            ->where('event_type', '!=', BehaviourEventType::OrderCompleted->value)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get([
                'id',
                'event_type',
                'product_id',
                'product_category_id',
                'product_variant_id',
                'metadata',
                'occurred_at',
            ]);
    }

    /**
     * Completed orders for a customer with items and add-ons (no cost fields needed).
     *
     * @return Collection<int, Order>
     */
    public function completedOrdersForCustomer(int $customerId, CarbonInterface $since): Collection
    {
        return Order::query()
            ->where('customer_id', $customerId)
            ->where('status', OrderStatus::Completed)
            ->where(function ($query) use ($since): void {
                $query->where('completed_at', '>=', $since)
                    ->orWhere(function ($inner) use ($since): void {
                        $inner->whereNull('completed_at')
                            ->where('updated_at', '>=', $since);
                    });
            })
            ->with([
                'items:id,order_id,product_id,product_variant_id,quantity',
                'items.addOns:id,order_item_id,add_on_id,quantity',
                'items.product:id,product_category_id',
            ])
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get([
                'id',
                'customer_id',
                'status',
                'total_amount',
                'completed_at',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, list<int>> product_id => flavour_ids
     */
    public function flavourIdsByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = DB::table('product_flavour_product')
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id')
            ->orderBy('product_flavour_id')
            ->get(['product_id', 'product_flavour_id']);

        $map = [];

        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            $map[$productId] ??= [];
            $map[$productId][] = (int) $row->product_flavour_id;
        }

        return $map;
    }

    /**
     * @return Collection<int, PersonalisationProfile>
     */
    public function staleProfiles(CarbonInterface $staleBefore, int $limit = 100): Collection
    {
        return PersonalisationProfile::query()
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('calculated_at')
                    ->orWhere('calculated_at', '<', $staleBefore);
            })
            ->orderBy('calculated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Claimed visitor keys for a customer (P2.1 ownership).
     *
     * @return list<string>
     */
    public function claimedVisitorKeysForCustomer(int $customerId): array
    {
        return DB::table('customer_visitor_identities')
            ->where('customer_id', $customerId)
            ->orderBy('id')
            ->pluck('visitor_key')
            ->map(static fn ($key): string => (string) $key)
            ->all();
    }
}
