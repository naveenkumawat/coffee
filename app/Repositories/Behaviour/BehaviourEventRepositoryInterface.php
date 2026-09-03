<?php

namespace App\Repositories\Behaviour;

use App\Models\CustomerBehaviourEvent;
use App\Models\CustomerVisitorIdentity;
use App\Models\User;
use Carbon\CarbonInterface;

interface BehaviourEventRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CustomerBehaviourEvent;

    public function findByIdempotencyKey(string $key): ?CustomerBehaviourEvent;

    public function findVisitorIdentity(string $visitorKey): ?CustomerVisitorIdentity;

    public function claimVisitorIdentity(string $visitorKey, User $customer): CustomerVisitorIdentity;

    public function attachUnclaimedVisitorEvents(string $visitorKey, User $customer): int;

    public function pruneOlderThan(CarbonInterface $cutoff, int $chunkSize = 500): int;

    /**
     * @return array{events: int, visitors: int, oldest_occurred_at: ?string, newest_occurred_at: ?string}
     */
    public function diagnosticsSummary(): array;

    public function productExists(int $productId): bool;

    public function categoryExists(int $categoryId): bool;

    public function variantExists(int $variantId): bool;
}
