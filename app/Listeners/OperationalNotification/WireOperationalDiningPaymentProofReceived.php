<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Dining\DiningPaymentProofReceived;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalDiningPaymentProofReceived
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(DiningPaymentProofReceived $event): void
    {
        $this->publisher->handleDiningPaymentProofReceived($event->session, $event->isResubmission);
    }
}
