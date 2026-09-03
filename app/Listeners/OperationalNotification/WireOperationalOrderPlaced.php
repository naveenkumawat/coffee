<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Order\OrderPlaced;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalOrderPlaced
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $this->publisher->handleOrderPlaced($event->order);
    }
}
