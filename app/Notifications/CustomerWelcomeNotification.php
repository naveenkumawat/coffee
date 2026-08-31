<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsCustomerMail;
use App\Support\CustomerAppUrl;
use App\Support\CustomerEmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerWelcomeNotification extends Notification implements ShouldQueue
{
    use BuildsCustomerMail;
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = CustomerEmailBrand::snapshot();
        $name = $notifiable->name ?? null;

        return $this->customerMail(
            subject: 'Welcome to '.$brand['business_name'],
            greeting: $this->greetingFor(is_string($name) ? $name : null),
            introLines: [
                'Your '.$brand['business_name'].' account is ready.',
                'Browse the menu and order ahead for takeaway or delivery.',
            ],
            actionText: 'Browse Menu',
            actionUrl: CustomerAppUrl::menu(),
            outroLines: [
                'If you did not create this account, please contact the café using the details below.',
            ],
        );
    }
}
