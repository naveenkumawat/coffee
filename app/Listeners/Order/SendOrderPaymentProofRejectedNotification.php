<?php

namespace App\Listeners\Order;

use App\Enums\CustomerNotificationType;
use App\Events\Order\OrderPaymentProofRejected;
use App\Notifications\OrderCustomerNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;
use App\Support\OrderCustomerMailRecipient;

class SendOrderPaymentProofRejectedNotification
{
    public function __construct(
        protected CustomerNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(OrderPaymentProofRejected $event): void
    {
        $order = $event->order->loadMissing(['items', 'customer']);
        $recipient = OrderCustomerMailRecipient::resolve($order);

        if ($recipient === null) {
            return;
        }

        $stamp = now()->format('YmdHis');
        $reason = $event->customerFacingReason;

        $this->dispatcher->sendOnce(
            CustomerNotificationType::PaymentProofRejected,
            'payment_proof_rejected:'.$order->getKey().':'.$stamp,
            $recipient['email'] ?? '',
            new OrderCustomerNotification(
                $order,
                CustomerNotificationType::PaymentProofRejected,
                $reason,
            ),
            $recipient['customer'],
            $order,
            $reason,
        );
    }
}
