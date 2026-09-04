<?php

namespace App\Services\Dining;

use App\Enums\DiningServiceRequestType;
use App\Models\DiningServiceRequest;
use App\Models\DiningSession;
use App\Models\User;
use Illuminate\Support\Collection;

interface DiningServiceRequestServiceInterface
{
    public function createOrderAssistance(DiningSession $session, User $customer): DiningServiceRequest;

    public function currentForSession(DiningSession $session, ?DiningServiceRequestType $type = null): ?DiningServiceRequest;

    public function cancel(DiningServiceRequest $request, User $customer): DiningServiceRequest;

    public function claim(DiningServiceRequest $request, User $waiter): DiningServiceRequest;

    public function complete(
        DiningServiceRequest $request,
        ?User $actor = null,
        string $reason = 'waiter_marked_done',
    ): DiningServiceRequest;

    public function escalateIfDue(DiningServiceRequest $request): DiningServiceRequest;

    /**
     * Complete open order_assistance requests when a waiter places a round.
     */
    public function completeOpenOrderAssistanceForWaiterRound(DiningSession $session, User $actor): void;

    /**
     * Cancel/complete open order_assistance when the customer self-orders.
     */
    public function resolveOpenOrderAssistanceForCustomerSelfOrder(DiningSession $session, User $actor): void;

    /**
     * @return Collection<int, DiningServiceRequest>
     */
    public function openRequestsForWaiter(?User $waiter = null): Collection;

    public function pendingCountForWaiter(?User $waiter = null): int;

    public function resolvePreferredWaiter(DiningSession $session): ?User;
}
