<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Order\OrderPreparationStatusChanged;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalOrderPreparationStatusChanged
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(OrderPreparationStatusChanged $event): void
    {
        $this->publisher->handlePreparationStatusChanged(
            $event->ticket,
            $event->fromStatus,
            $event->toStatus,
        );
    }
}
