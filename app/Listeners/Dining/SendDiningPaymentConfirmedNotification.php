<?php

namespace App\Listeners\Dining;

use App\Enums\CustomerNotificationType;
use App\Events\Dining\DiningPaymentConfirmed;
use App\Notifications\DiningSessionCustomerNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;

class SendDiningPaymentConfirmedNotification
{
    public function __construct(
        protected CustomerNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(DiningPaymentConfirmed $event): void
    {
        $session = $event->session->loadMissing('customer');
        $customer = $session->customer;
        $email = strtolower(trim((string) ($customer?->email ?? '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $this->dispatcher->sendOnce(
            CustomerNotificationType::DiningPaymentConfirmed,
            'dining_payment_confirmed:'.$session->getKey(),
            $email,
            new DiningSessionCustomerNotification($session, CustomerNotificationType::DiningPaymentConfirmed),
            $customer,
        );
    }
}
