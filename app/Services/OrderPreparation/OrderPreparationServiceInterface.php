<?php

namespace App\Services\OrderPreparation;

use App\Enums\OrderPreparationStatus;
use App\Enums\PreparationStation;
use App\Models\Order;
use App\Models\OrderPreparation;
use App\Models\User;
use Illuminate\Support\Collection;

interface OrderPreparationServiceInterface
{
    public function createTicketsForOrder(Order $order): void;

    public function cancelTicketsForOrder(Order $order, ?User $actor = null): void;

    public function transition(
        OrderPreparation $ticket,
        User $actor,
        OrderPreparationStatus $next,
        ?string $notes = null,
    ): OrderPreparation;

    public function syncOrderStatusFromTickets(Order $order, User $systemActor): void;

    /**
     * @return Collection<int, OrderPreparation>
     */
    public function queueForStation(PreparationStation $station, ?string $statusFilter = null): Collection;
}
