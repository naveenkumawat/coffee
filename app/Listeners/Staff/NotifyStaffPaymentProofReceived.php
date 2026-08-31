<?php

namespace App\Listeners\Staff;

use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationType;
use App\Events\Order\OrderPaymentProofReceived;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;

class NotifyStaffPaymentProofReceived
{
    public function __construct(
        protected StaffNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(OrderPaymentProofReceived $event): void
    {
        $order = $event->order->loadMissing(['items', 'customer']);
        $type = $event->isResubmission
            ? StaffNotificationType::PaymentProofResubmitted
            : StaffNotificationType::PaymentProofReceived;
        $stamp = $order->payment_proof_uploaded_at?->format('YmdHis') ?: (string) time();

        $this->dispatcher->notify(
            $type,
            'staff:'.$type->value.':'.$order->getKey().':'.$stamp,
            StaffNotificationAudience::Administrators,
            StaffNotificationContext::forOrder($order),
            sendEmail: true,
        );
    }
}
