<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Dining\DiningRoundPlaced;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalDiningRoundPlaced
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(DiningRoundPlaced $event): void
    {
        $this->publisher->handleDiningRoundPlaced($event->order, $event->session);
    }
}
