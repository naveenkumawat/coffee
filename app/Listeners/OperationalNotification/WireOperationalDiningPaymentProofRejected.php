<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Dining\DiningPaymentProofRejected;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalDiningPaymentProofRejected
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(DiningPaymentProofRejected $event): void
    {
        $this->publisher->handleDiningPaymentProofRejected($event->session, $event->customerFacingReason);
    }
}
