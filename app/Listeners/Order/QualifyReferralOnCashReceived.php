<?php

namespace App\Listeners\Order;

use App\Enums\PaymentStatus;
use App\Events\Order\OrderCashReceived;
use App\Services\Referral\ReferralServiceInterface;

class QualifyReferralOnCashReceived
{
    public function __construct(
        protected ReferralServiceInterface $referrals,
    ) {}

    public function handle(OrderCashReceived $event): void
    {
        $order = $event->order->fresh() ?? $event->order;

        if ($order->payment_status !== PaymentStatus::Confirmed) {
            return;
        }

        $this->referrals->qualifyOrderIfEligible($order);
    }
}
