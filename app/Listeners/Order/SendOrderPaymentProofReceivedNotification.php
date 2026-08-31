<?php

namespace App\Listeners\Order;

use App\Enums\CustomerNotificationType;
use App\Events\Order\OrderPaymentProofReceived;
use App\Notifications\OrderCustomerNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;
use App\Support\OrderCustomerMailRecipient;

class SendOrderPaymentProofReceivedNotification
{
    public function __construct(
        protected CustomerNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(OrderPaymentProofReceived $event): void
    {
        $order = $event->order->loadMissing(['items', 'customer']);
        $recipient = OrderCustomerMailRecipient::resolve($order);

        if ($recipient === null) {
            return;
        }

        $stamp = $order->payment_proof_uploaded_at?->format('YmdHis') ?: (string) time();

        $this->dispatcher->sendOnce(
            CustomerNotificationType::PaymentProofReceived,
            'payment_proof_received:'.$order->getKey().':'.$stamp,
            $recipient['email'] ?? '',
            new OrderCustomerNotification($order, CustomerNotificationType::PaymentProofReceived),
            $recipient['customer'],
            $order,
        );
    }
}
