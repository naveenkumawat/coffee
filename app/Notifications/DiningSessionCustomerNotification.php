<?php

namespace App\Notifications;

use App\Enums\CustomerNotificationType;
use App\Models\DiningSession;
use App\Notifications\Concerns\BuildsCustomerMail;
use App\Support\CustomerAppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class DiningSessionCustomerNotification extends Notification implements ShouldQueue
{
    use BuildsCustomerMail;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public DiningSession $session,
        public CustomerNotificationType $type,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $session = $this->session->loadMissing('cafeTable');
        $name = $notifiable->name ?? $session->customer_name_snapshot;
        $table = $session->tableDisplayLabel();
        $total = number_format((float) ($session->total_amount ?? 0), 2, '.', '');
        $url = CustomerAppUrl::to('/dining/sessions/'.$session->getKey());

        [$subject, $intro, $action] = match ($this->type) {
            CustomerNotificationType::DiningBillReady => [
                'Your dining bill is ready',
                [
                    'Your bill for '.$table.' is ready.',
                    'Amount due: '.$total,
                    'Choose cash or UPI to finish payment.',
                ],
                'View bill',
            ],
            CustomerNotificationType::DiningPaymentConfirmed => [
                'Dining payment confirmed',
                [
                    'Payment for '.$table.' is confirmed. Thank you!',
                    'Paid total: '.$total,
                ],
                'View session',
            ],
            default => [
                'Dining session update',
                ['There is an update for your dining session at '.$table.'.'],
                'View session',
            ],
        };

        return $this->customerMail(
            subject: $subject,
            greeting: $this->greetingFor(is_string($name) ? $name : null),
            introLines: $intro,
            actionText: $action,
            actionUrl: $url,
            outroLines: ['See you at The88Coffees.'],
            extra: [
                'statusLabel' => $this->type->label(),
                'statusTone' => 'success',
            ],
        );
    }
}
