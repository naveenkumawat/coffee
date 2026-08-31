<?php

namespace App\Listeners\Order;

use App\Enums\CustomerNotificationType;
use App\Events\Order\OrderCashReceived;
use App\Notifications\OrderCustomerNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;
use App\Support\OrderCustomerMailRecipient;

class SendOrderCashReceivedNotification
{
    public function __construct(
        protected CustomerNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(OrderCashReceived $event): void
    {
        $order = $event->order->loadMissing(['items', 'customer']);
        $recipient = OrderCustomerMailRecipient::resolve($order);

        if ($recipient === null) {
            return;
        }

        $this->dispatcher->sendOnce(
            CustomerNotificationType::PaymentConfirmed,
            'order_cash_received:'.$order->getKey(),
            $recipient['email'] ?? '',
            new OrderCustomerNotification($order, CustomerNotificationType::PaymentConfirmed),
            $recipient['customer'],
            $order,
        );
    }
}
