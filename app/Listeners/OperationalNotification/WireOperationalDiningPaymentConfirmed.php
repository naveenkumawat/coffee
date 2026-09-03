<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Dining\DiningPaymentConfirmed;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalDiningPaymentConfirmed
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(DiningPaymentConfirmed $event): void
    {
        $this->publisher->handleDiningPaymentConfirmed($event->session);
    }
}
