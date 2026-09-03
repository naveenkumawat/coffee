<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Order\OrderStatusChanged;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalOrderStatusChanged
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        $this->publisher->handleOrderStatusChanged(
            $event->order,
            $event->fromStatus,
            $event->toStatus,
        );
    }
}
