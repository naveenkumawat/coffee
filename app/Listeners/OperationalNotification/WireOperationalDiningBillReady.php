<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Dining\DiningBillReady;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalDiningBillReady
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(DiningBillReady $event): void
    {
        $this->publisher->handleDiningBillReady($event->session);
    }
}
