<?php

namespace App\Listeners\Customer;

use App\Enums\CustomerNotificationType;
use App\Events\Customer\CustomerRegistered;
use App\Notifications\CustomerWelcomeNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;

class SendCustomerWelcomeNotification
{
    public function __construct(
        protected CustomerNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(CustomerRegistered $event): void
    {
        $customer = $event->customer;

        $this->dispatcher->sendOnce(
            CustomerNotificationType::Welcome,
            'welcome:'.$customer->getKey(),
            (string) $customer->email,
            new CustomerWelcomeNotification,
            $customer,
        );
    }
}
