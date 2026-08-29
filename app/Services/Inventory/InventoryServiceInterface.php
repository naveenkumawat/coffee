<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Transfers\Inventory\InventoryTransactionTransferInterface;
use Illuminate\Database\Eloquent\Collection;

interface InventoryServiceInterface
{
    public function recordTransaction(InventoryTransactionTransferInterface $data): InventoryTransaction;

    public function backfillOpeningBalances(string $referenceType = 'seeder_opening_balance'): Collection;

    public function compatibleMeasurementUnitOptions(Ingredient $ingredient): array;
}
