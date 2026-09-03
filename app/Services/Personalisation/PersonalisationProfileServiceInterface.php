<?php

namespace App\Services\Personalisation;

use App\Models\PersonalisationProfile;
use App\Models\User;

interface PersonalisationProfileServiceInterface
{
    public function isBehaviourTrackingEnabled(): bool;

    public function getForCustomer(int $customerId): ?PersonalisationProfile;

    public function getForVisitor(string $visitorKey): ?PersonalisationProfile;

    /**
     * @return array<string, mixed>|null
     */
    public function profilePayloadForCustomer(int $customerId): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function profilePayloadForVisitor(string $visitorKey): ?array;

    public function rebuildForCustomer(int $customerId): PersonalisationProfile;

    public function rebuildForVisitor(string $visitorKey): PersonalisationProfile;

    public function resetForCustomer(int $customerId): bool;

    public function resetForVisitor(string $visitorKey): bool;

    public function dispatchRebuildForCustomer(int $customerId): void;

    public function dispatchRebuildForVisitor(string $visitorKey): void;

    public function afterVisitorMerged(string $visitorKey, User $customer): void;

    public function rebuildStale(int $limit = 100): int;
}
