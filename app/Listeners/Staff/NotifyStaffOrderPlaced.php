<?php

namespace App\Listeners\Staff;

use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationType;
use App\Events\Order\OrderPlaced;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;

class NotifyStaffOrderPlaced
{
    public function __construct(
        protected StaffNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing(['items', 'customer']);

        $this->dispatcher->notify(
            StaffNotificationType::OrderPlaced,
            'staff:order_placed:'.$order->getKey(),
            StaffNotificationAudience::Administrators,
            StaffNotificationContext::forOrder($order),
            sendEmail: true,
        );
    }
}
