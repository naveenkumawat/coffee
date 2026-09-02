<?php

namespace App\Listeners\Dining;

use App\Events\Dining\DiningPaymentConfirmed;
use App\Services\Referral\ReferralServiceInterface;

class QualifyReferralOnDiningPaymentConfirmed
{
    public function __construct(
        protected ReferralServiceInterface $referrals,
    ) {}

    public function handle(DiningPaymentConfirmed $event): void
    {
        $this->referrals->qualifyDiningSessionIfEligible(
            $event->session->fresh() ?? $event->session,
        );
    }
}
