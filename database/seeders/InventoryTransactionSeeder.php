<?php

namespace Database\Seeders;

use App\Enums\InventoryTransactionType;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\Inventory\InventoryServiceInterface;
use App\Transfers\Inventory\InventoryTransactionTransfer;
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
        $this->seedDemoMovements();
    }

    protected function seedDemoMovements(): void
    {
        if (InventoryTransaction::query()->where('reference_type', 'seeder_demo_movement')->exists()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@coffee.local')->first();

        foreach ($this->movements() as $index => $movement) {
            $ingredient = Ingredient::query()->where('name', $movement['ingredient'])->first();

            if (! $ingredient) {
                continue;
            }

            $transfer = new InventoryTransactionTransfer;
            $transfer->setIngredientId($ingredient->id);
            $transfer->setTransactionType($movement['type']->value);
            $transfer->setQuantity($movement['quantity']);
            $transfer->setMeasurementUnit($movement['unit'] ?? $ingredient->measurement_unit->value);
            $transfer->setReferenceType('seeder_demo_movement');
            $transfer->setReferenceId($index + 1);
            $transfer->setNotes($movement['notes']);
            $transfer->setCreatedBy($admin?->id);

            $this->inventory->recordTransaction($transfer);
        }
    }

    /**
     * @return list<array{ingredient: string, type: InventoryTransactionType, quantity: string, unit?: string, notes: string}>
     */
    protected function movements(): array
    {
        return [
            [
                'ingredient' => 'Full Fat Milk',
                'type' => InventoryTransactionType::Purchase,
                'quantity' => '4.000',
                'notes' => 'DEMO: Morning dairy purchase delivery.',
            ],
            [
                'ingredient' => 'Davidoff Espresso',
                'type' => InventoryTransactionType::StockAdded,
                'quantity' => '250.000',
                'notes' => 'DEMO: Restocked premium espresso after weekend service.',
            ],
            [
                'ingredient' => 'Vanilla Ice Cream',
                'type' => InventoryTransactionType::Wastage,
                'quantity' => '0.500',
                'notes' => 'DEMO: Softened tub discarded after freezer hiccup.',
            ],
            [
                'ingredient' => 'Hazelnut Syrup',
                'type' => InventoryTransactionType::Damage,
                'quantity' => '1.000',
                'notes' => 'DEMO: Bottle cracked in storage; recorded as damage.',
            ],
            [
                'ingredient' => 'Caramel Syrup',
                'type' => InventoryTransactionType::Expiry,
                'quantity' => '1.000',
                'notes' => 'DEMO: Expired syrup bottle removed from service.',
            ],
            [
                'ingredient' => 'Chocolate Sauce',
                'type' => InventoryTransactionType::ManualReduction,
                'quantity' => '0.200',
                'notes' => 'DEMO: Manual count correction after prep spill.',
            ],
            [
                'ingredient' => '12oz Hot Cups',
                'type' => InventoryTransactionType::ManualAddition,
                'quantity' => '50.000',
                'notes' => 'DEMO: Found spare sleeve of cups in back stock.',
            ],
            [
                'ingredient' => 'Assam Tea Leaves',
                'type' => InventoryTransactionType::ManualAdjustment,
                'quantity' => '3200.000',
                'unit' => 'g',
                'notes' => 'DEMO: Absolute stock correction after physical count.',
            ],
        ];
    }
}
