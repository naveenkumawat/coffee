<?php

namespace App\Notifications;

use App\Models\CustomerReward;
use App\Notifications\Concerns\BuildsCustomerMail;
use App\Support\CustomerAppUrl;
use App\Support\CustomerEmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralRewardEarnedNotification extends Notification implements ShouldQueue
{
    use BuildsCustomerMail;
    use Queueable;

    public function __construct(public CustomerReward $reward)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = CustomerEmailBrand::snapshot();
        $name = $notifiable->name ?? null;
        $title = $this->reward->displayTitle();
        $expires = $this->reward->expires_at?->timezone(config('app.timezone'))->format('d M Y');

        $intro = [
            'Thanks for referring a friend to '.$brand['business_name'].'.',
            'Your reward is ready: '.$title.'.',
        ];

        if ($expires !== null) {
            $intro[] = 'Redeem it before '.$expires.'.';
        }

        return $this->customerMail(
            subject: 'Your referral reward is ready',
            greeting: $this->greetingFor(is_string($name) ? $name : null),
            introLines: $intro,
            actionText: 'View rewards',
            actionUrl: CustomerAppUrl::to('/account/rewards'),
            outroLines: [
                'Free Drink rewards waive the item price; applicable GST remains payable.',
            ],
        );
    }
}
