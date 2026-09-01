<?php

namespace App\Listeners\Dining;

use App\Enums\CustomerNotificationType;
use App\Events\Dining\DiningBillReady;
use App\Notifications\DiningSessionCustomerNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;

class SendDiningBillReadyNotification
{
    public function __construct(
        protected CustomerNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(DiningBillReady $event): void
    {
        $session = $event->session->loadMissing('customer');
        $customer = $session->customer;
        $email = strtolower(trim((string) ($customer?->email ?? '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $this->dispatcher->sendOnce(
            CustomerNotificationType::DiningBillReady,
            'dining_bill_ready:'.$session->getKey(),
            $email,
            new DiningSessionCustomerNotification($session, CustomerNotificationType::DiningBillReady),
            $customer,
        );
    }
}
