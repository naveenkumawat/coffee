<?php

namespace App\Events\Dining;

use App\Models\DiningSession;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiningRoundPlaced
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public DiningSession $session,
    ) {}
}
