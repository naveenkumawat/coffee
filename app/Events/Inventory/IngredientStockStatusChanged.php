<?php

namespace App\Events\Inventory;

use App\Enums\InventoryStockStatus;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IngredientStockStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Ingredient $ingredient,
        public InventoryStockStatus $fromStatus,
        public InventoryStockStatus $toStatus,
        public InventoryTransaction $transaction,
    ) {}
}
