<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerResetPasswordNotification extends Notification
{
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
        $url = $this->getCustomerResetUrl($notifiable);

        return (new MailMessage)
            ->subject('Reset Your Coffee Account Password')
            ->line('You requested a password reset for your Coffee customer account.')
            ->action('Reset Password', $url)
            ->line('If you did not request a password reset, no further action is required.');
    }

    protected function getCustomerResetUrl(object $notifiable): string
    {
        $baseUrl = rtrim((string) config('coffee.pwa.url', config('app.url')), '/');
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return "{$baseUrl}/reset-password?{$query}";
    }
}
