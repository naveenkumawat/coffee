<?php

namespace App\Repositories\Personalisation;

use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\PersonalisationProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface PersonalisationProfileRepositoryInterface
{
    public function findForCustomer(int $customerId): ?PersonalisationProfile;

    public function findForVisitor(string $visitorKey): ?PersonalisationProfile;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertCustomerProfile(int $customerId, array $attributes): PersonalisationProfile;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertVisitorProfile(string $visitorKey, array $attributes): PersonalisationProfile;

    public function deleteForCustomer(int $customerId): bool;

    public function deleteForVisitor(string $visitorKey): bool;

    /**
     * @return Collection<int, CustomerBehaviourEvent>
     */
    public function behaviourEventsForCustomer(int $customerId, CarbonInterface $since): Collection;

    /**
     * @return Collection<int, CustomerBehaviourEvent>
     */
    public function behaviourEventsForVisitor(string $visitorKey, CarbonInterface $since): Collection;

    /**
     * @return Collection<int, Order>
     */
    public function completedOrdersForCustomer(int $customerId, CarbonInterface $since): Collection;

    /**
     * @param  list<int>  $productIds
     * @return array<int, list<int>>
     */
    public function flavourIdsByProductIds(array $productIds): array;

    /**
     * @return Collection<int, PersonalisationProfile>
     */
    public function staleProfiles(CarbonInterface $staleBefore, int $limit = 100): Collection;

    /**
     * @return list<string>
     */
    public function claimedVisitorKeysForCustomer(int $customerId): array;
}
