<?php

namespace App\Listeners\Order;

use App\Enums\CustomerNotificationType;
use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Notifications\OrderCustomerNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;
use App\Support\OrderCustomerMailRecipient;

class SendOrderStatusChangedNotification
{
    public function __construct(
        protected CustomerNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        $type = $this->typeFor($event->toStatus);

        if ($type === null) {
            return;
        }

        $order = $event->order->loadMissing(['items', 'customer']);
        $recipient = OrderCustomerMailRecipient::resolve($order);

        if ($recipient === null) {
            return;
        }

        $reason = in_array($type, [CustomerNotificationType::OrderCancelled, CustomerNotificationType::OrderRejected], true)
            ? $this->safeCustomerReason($event->customerFacingNotes)
            : null;

        $this->dispatcher->sendOnce(
            $type,
            'order_status:'.$order->getKey().':'.$event->toStatus->value,
            $recipient['email'] ?? '',
            new OrderCustomerNotification($order, $type, $reason),
            $recipient['customer'],
            $order,
            $reason,
        );
    }

    protected function typeFor(OrderStatus $status): ?CustomerNotificationType
    {
        return match ($status) {
            OrderStatus::PaymentConfirmed => CustomerNotificationType::PaymentConfirmed,
            OrderStatus::Accepted => CustomerNotificationType::OrderAccepted,
            OrderStatus::Preparing => CustomerNotificationType::OrderPreparing,
            OrderStatus::ReadyForPickup => CustomerNotificationType::OrderReady,
            OrderStatus::Completed => CustomerNotificationType::OrderCompleted,
            OrderStatus::Cancelled => CustomerNotificationType::OrderCancelled,
            OrderStatus::Rejected => CustomerNotificationType::OrderRejected,
            default => null,
        };
    }

    protected function safeCustomerReason(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        if ($notes === '') {
            return null;
        }

        // Status history notes may include internal phrasing; only pass short plain text.
        $notes = strip_tags($notes);

        return mb_strlen($notes) > 500 ? mb_substr($notes, 0, 500).'…' : $notes;
    }
}
