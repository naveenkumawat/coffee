<?php

namespace App\Events\Inventory;

use App\Enums\InventoryRefillRequestStatus;
use App\Models\InventoryRefillRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryRefillRequestStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public InventoryRefillRequest $refillRequest,
        public InventoryRefillRequestStatus $fromStatus,
        public InventoryRefillRequestStatus $toStatus,
    ) {}
}
