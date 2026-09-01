<?php

namespace App\Listeners\Order;

use App\Enums\CustomerNotificationType;
use App\Events\Order\OrderPlaced;
use App\Notifications\OrderCustomerNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;
use App\Support\OrderCustomerMailRecipient;

class SendOrderPlacedNotification
{
    public function __construct(
        protected CustomerNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing(['items', 'customer']);

        if ($order->dining_session_id) {
            return;
        }

        $recipient = OrderCustomerMailRecipient::resolve($order);

        if ($recipient === null) {
            return;
        }

        $this->dispatcher->sendOnce(
            CustomerNotificationType::OrderPlaced,
            'order_placed:'.$order->getKey(),
            $recipient['email'] ?? '',
            new OrderCustomerNotification($order, CustomerNotificationType::OrderPlaced),
            $recipient['customer'],
            $order,
        );
    }
}
