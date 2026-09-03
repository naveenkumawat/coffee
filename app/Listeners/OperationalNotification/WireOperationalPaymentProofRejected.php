<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Order\OrderPaymentProofRejected;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalPaymentProofRejected
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(OrderPaymentProofRejected $event): void
    {
        $this->publisher->handlePaymentProofRejected($event->order);
    }
}
