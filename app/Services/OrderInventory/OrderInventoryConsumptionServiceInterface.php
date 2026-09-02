<?php

namespace App\Services\OrderInventory;

use App\Models\Order;
use App\Models\User;

interface OrderInventoryConsumptionServiceInterface
{
    /**
     * Consume recipe ingredients for an accepted order/round exactly once.
     * Must run inside the caller's DB transaction before preparation tickets are created.
     */
    public function consumeForAcceptedOrder(Order $order, ?User $actor = null): void;

    /**
     * Create SALE_REVERSAL ledger rows when early cancellation is allowed.
     * Idempotent. Never deletes original SALE_CONSUMPTION rows.
     */
    public function reverseForCancelledOrder(Order $order, ?User $actor = null): void;

    /**
     * True when any non-cancelled preparation ticket is Preparing or Ready.
     */
    public function hasMaterialPreparationStarted(Order $order): bool;
}
