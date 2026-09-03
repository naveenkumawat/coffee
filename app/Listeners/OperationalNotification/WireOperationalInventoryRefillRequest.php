<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Inventory\InventoryRefillRequestCreated;
use App\Events\Inventory\InventoryRefillRequestStatusChanged;
use App\Services\OperationalNotification\OperationalInventoryNotificationPublisher;

class WireOperationalInventoryRefillRequest
{
    public function __construct(
        protected OperationalInventoryNotificationPublisher $publisher,
    ) {}

    public function handleCreated(InventoryRefillRequestCreated $event): void
    {
        $this->publisher->handleRefillCreated($event->refillRequest);
    }

    public function handleStatusChanged(InventoryRefillRequestStatusChanged $event): void
    {
        $this->publisher->handleRefillStatusChanged(
            $event->refillRequest,
            $event->fromStatus,
            $event->toStatus,
        );
    }
}
