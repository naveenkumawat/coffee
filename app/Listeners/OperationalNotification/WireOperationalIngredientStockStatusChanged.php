<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Inventory\IngredientStockStatusChanged;
use App\Services\OperationalNotification\OperationalInventoryNotificationPublisher;

class WireOperationalIngredientStockStatusChanged
{
    public function __construct(
        protected OperationalInventoryNotificationPublisher $publisher,
    ) {}

    public function handle(IngredientStockStatusChanged $event): void
    {
        $this->publisher->handleStockStatusChanged(
            $event->ingredient,
            $event->fromStatus,
            $event->toStatus,
            $event->transaction,
        );
    }
}
