<?php

namespace App\Services\Behaviour;

use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\User;

interface BehaviourEventServiceInterface
{
    public function isEnabled(): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{accepted: bool, reason?: string, event_id?: int|null}
     */
    public function ingestClientEvent(array $payload, ?User $customer = null): array;

    /**
     * @return array{merged: bool, attached: int, reason?: string}
     */
    public function mergeVisitorToCustomer(string $visitorKey, User $customer): array;

    public function recordOrderCompleted(Order $order): ?CustomerBehaviourEvent;

    public function pruneExpired(): int;

    /**
     * @return array{events: int, visitors: int, oldest_occurred_at: ?string, newest_occurred_at: ?string}
     */
    public function diagnosticsSummary(): array;
}
