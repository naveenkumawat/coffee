<?php

namespace Database\Seeders;

use App\Services\Inventory\InventoryServiceInterface;
use Illuminate\Database\Seeder;

class InventoryTransactionSeeder extends Seeder
{
    public function __construct(
        protected InventoryServiceInterface $inventory,
    ) {}

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $this->inventory->backfillOpeningBalances();
    }
}
