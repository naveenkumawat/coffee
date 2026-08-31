<?php

namespace App\Events\Inventory;

use App\Models\InventoryRefillRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryRefillRequestCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public InventoryRefillRequest $refillRequest) {}
}
