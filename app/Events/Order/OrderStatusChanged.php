<?php

namespace App\Events\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public OrderStatus $fromStatus,
        public OrderStatus $toStatus,
        public ?string $customerFacingNotes = null,
    ) {}
}
