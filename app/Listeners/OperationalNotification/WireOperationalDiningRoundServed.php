<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Dining\DiningRoundServed;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalDiningRoundServed
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(DiningRoundServed $event): void
    {
        $this->publisher->handleDiningRoundServed($event->order, $event->session);
    }
}
