<?php

namespace App\Listeners\Staff;

use App\Enums\OrderStatus;
use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationType;
use App\Events\Order\OrderStatusChanged;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;

class NotifyStaffOrderStatusChanged
{
    public function __construct(
        protected StaffNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing(['items', 'customer']);
        $context = StaffNotificationContext::forOrder($order);

        if ($event->toStatus === OrderStatus::PaymentConfirmed) {
            $this->dispatcher->notify(
                StaffNotificationType::PaymentConfirmed,
                'staff:order_status:'.$order->getKey().':'.$event->toStatus->value,
                StaffNotificationAudience::Baristas,
                $context,
                sendEmail: true,
            );

            return;
        }

        if ($event->toStatus === OrderStatus::Accepted) {
            $this->dispatcher->notify(
                StaffNotificationType::OrderAccepted,
                'staff:order_status:'.$order->getKey().':'.$event->toStatus->value,
                StaffNotificationAudience::Baristas,
                $context,
                sendEmail: false,
            );

            return;
        }

        if (! in_array($event->toStatus, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
            return;
        }

        $type = $event->toStatus === OrderStatus::Rejected
            ? StaffNotificationType::OrderRejected
            : StaffNotificationType::OrderCancelled;

        $uniqueKey = 'staff:order_status:'.$order->getKey().':'.$event->toStatus->value;

        $this->dispatcher->notify(
            $type,
            $uniqueKey,
            StaffNotificationAudience::Administrators,
            $context,
            sendEmail: false,
        );

        if ($this->wasOperationallyAccepted($event->fromStatus)) {
            $this->dispatcher->notify(
                $type,
                $uniqueKey,
                StaffNotificationAudience::Baristas,
                $context,
                sendEmail: false,
            );
        }
    }

    protected function wasOperationallyAccepted(OrderStatus $fromStatus): bool
    {
        return in_array($fromStatus, [
            OrderStatus::Accepted,
            OrderStatus::Preparing,
            OrderStatus::ReadyForPickup,
        ], true);
    }
}
