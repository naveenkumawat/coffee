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
        $order = $event->order->loadMissing(['items', 'customer', 'diningSession.cafeTable']);

        if ($order->dining_session_id) {
            $this->dispatcher->notify(
                StaffNotificationType::OrderPlaced,
                'staff:dining_round_placed:'.$order->getKey(),
                StaffNotificationAudience::Baristas,
                StaffNotificationContext::forOrder($order),
                sendEmail: false,
            );

            $this->dispatcher->notify(
                StaffNotificationType::OrderPlaced,
                'staff:dining_round_placed_waiter:'.$order->getKey(),
                StaffNotificationAudience::Waiters,
                StaffNotificationContext::forOrder($order),
                sendEmail: false,
            );

            $this->dispatcher->notify(
                StaffNotificationType::OrderPlaced,
                'staff:dining_round_placed_admin:'.$order->getKey(),
                StaffNotificationAudience::Administrators,
                StaffNotificationContext::forOrder($order),
                sendEmail: true,
            );

            return;
        }

        $this->dispatcher->notify(
            StaffNotificationType::OrderPlaced,
            'staff:order_placed:'.$order->getKey(),
            StaffNotificationAudience::Administrators,
            StaffNotificationContext::forOrder($order),
            sendEmail: true,
        );
    }
}
