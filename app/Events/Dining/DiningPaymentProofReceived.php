<?php

namespace App\Events\Dining;

use App\Models\DiningSession;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiningPaymentProofReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public DiningSession $session,
        public bool $isResubmission = false,
    ) {}
}
