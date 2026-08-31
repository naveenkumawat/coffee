<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsCustomerMail;
use App\Support\CustomerAppUrl;
use App\Support\CustomerEmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerPasswordChangedNotification extends Notification implements ShouldQueue
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
            subject: 'Your '.$brand['business_name'].' password was changed',
            greeting: $this->greetingFor(is_string($name) ? $name : null),
            introLines: [
                'Your account password was changed successfully.',
                'If you did not make this change, contact the café right away using the details below.',
            ],
            actionText: 'Open Account',
            actionUrl: CustomerAppUrl::to('/account'),
            outroLines: [
                'For your security, this message does not include your password.',
            ],
        );
    }
}
