<?php

namespace App\Events\Order;

use App\Enums\OrderPreparationStatus;
use App\Models\OrderPreparation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPreparationStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public OrderPreparation $ticket,
        public ?OrderPreparationStatus $fromStatus,
        public OrderPreparationStatus $toStatus,
        public ?string $notes = null,
    ) {}
}
