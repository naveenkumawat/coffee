<?php

namespace App\Notifications\Concerns;

use App\Support\CustomerEmailBrand;
use Illuminate\Notifications\Messages\MailMessage;

trait BuildsCustomerMail
{
    /**
     * @param  list<string>  $introLines
     * @param  list<string>  $outroLines
     * @param  array<string, mixed>  $extra
     */
    protected function customerMail(
        string $subject,
        string $greeting,
        array $introLines,
        ?string $actionText = null,
        ?string $actionUrl = null,
        array $outroLines = [],
        array $extra = [],
    ): MailMessage {
        $brand = CustomerEmailBrand::snapshot();

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.customer.transactional', array_merge([
                'subject' => $subject,
                'brand' => $brand,
                'greeting' => $greeting,
                'introLines' => $introLines,
                'actionText' => $actionText,
                'actionUrl' => $actionUrl,
                'outroLines' => $outroLines,
                'order' => null,
                'statusLabel' => null,
                'statusTone' => 'neutral',
            ], $extra));
    }

    protected function greetingFor(?string $name): string
    {
        $first = CustomerEmailBrand::firstName($name);

        return $first ? "Hi {$first}," : 'Hi,';
    }
}
