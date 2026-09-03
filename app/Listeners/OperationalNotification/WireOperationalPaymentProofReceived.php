<?php

namespace App\Listeners\OperationalNotification;

use App\Events\Order\OrderPaymentProofReceived;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;

class WireOperationalPaymentProofReceived
{
    public function __construct(
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function handle(OrderPaymentProofReceived $event): void
    {
        $this->publisher->handlePaymentProofReceived($event->order, $event->isResubmission);
    }
}
