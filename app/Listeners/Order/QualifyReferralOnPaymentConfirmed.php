<?php

namespace App\Listeners\Order;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Services\Referral\ReferralServiceInterface;

class QualifyReferralOnPaymentConfirmed
{
    public function __construct(
        protected ReferralServiceInterface $referrals,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->toStatus !== OrderStatus::PaymentConfirmed) {
            return;
        }

        $this->referrals->qualifyOrderIfEligible($event->order->fresh() ?? $event->order);
    }
}
