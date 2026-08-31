<?php

namespace App\Listeners\Customer;

use App\Enums\CustomerNotificationType;
use App\Events\Customer\CustomerPasswordChanged;
use App\Notifications\CustomerPasswordChangedNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;

class SendCustomerPasswordChangedNotification
{
    public function __construct(
        protected CustomerNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(CustomerPasswordChanged $event): void
    {
        $customer = $event->customer;

        $this->dispatcher->sendOnce(
            CustomerNotificationType::PasswordChanged,
            'password_changed:'.$customer->getKey().':'.now()->format('YmdHis'),
            (string) $customer->email,
            new CustomerPasswordChangedNotification,
            $customer,
        );
    }
}
