<?php

namespace App\Events\Dining;

use App\Models\DiningSession;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiningPaymentConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public DiningSession $session,
        public User $actor,
    ) {}
}
