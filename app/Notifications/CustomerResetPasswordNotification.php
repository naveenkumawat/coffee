<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsCustomerMail;
use App\Support\CustomerAppUrl;
use App\Support\CustomerEmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerResetPasswordNotification extends Notification implements ShouldQueue
{
    use BuildsCustomerMail;
    use Queueable;

    public function __construct(
        protected string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = CustomerEmailBrand::snapshot();
        $name = $notifiable->name ?? null;
        $email = $notifiable->getEmailForPasswordReset();
        $url = CustomerAppUrl::resetPassword($this->token, $email);
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        return $this->customerMail(
            subject: 'Reset your '.$brand['business_name'].' password',
            greeting: $this->greetingFor(is_string($name) ? $name : null),
            introLines: [
                'We received a request to reset the password for your customer account.',
                'This reset link expires in '.$expireMinutes.' minutes.',
            ],
            actionText: 'Reset Password',
            actionUrl: $url,
            outroLines: [
                'If you did not request a password reset, you can ignore this email.',
            ],
        );
    }
}
