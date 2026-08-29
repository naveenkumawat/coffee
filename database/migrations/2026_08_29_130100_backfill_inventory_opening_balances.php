<?php

use App\Enums\InventoryTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ingredients')
            ->select([
                'ingredients.id',
                'ingredients.current_stock',
                'ingredients.base_measurement_unit',
            ])
            ->whereNull('ingredients.deleted_at')
            ->where('ingredients.current_stock', '>', 0)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('inventory_transactions')
                    ->whereColumn('inventory_transactions.ingredient_id', 'ingredients.id')
                    ->where('inventory_transactions.transaction_type', InventoryTransactionType::OpeningBalance->value)
                    ->where('inventory_transactions.reference_type', 'migration_opening_balance');
            })
            ->orderBy('ingredients.id')
            ->get()
            ->each(function (object $ingredient): void {
                $timestamp = now();

                DB::table('inventory_transactions')->insert([
                    'ingredient_id' => $ingredient->id,
                    'transaction_type' => InventoryTransactionType::OpeningBalance->value,
                    'quantity' => $ingredient->current_stock,
                    'base_quantity' => $ingredient->current_stock,
                    'measurement_unit' => $ingredient->base_measurement_unit,
                    'base_measurement_unit' => $ingredient->base_measurement_unit,
                    'stock_before' => '0.000',
                    'stock_after' => $ingredient->current_stock,
                    'reference_type' => 'migration_opening_balance',
                    'reference_id' => $ingredient->id,
                    'notes' => 'Backfilled opening stock from pre-ledger ingredient records.',
                    'created_by' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('inventory_transactions')
            ->where('transaction_type', InventoryTransactionType::OpeningBalance->value)
            ->where('reference_type', 'migration_opening_balance')
            ->delete();
    }
};
