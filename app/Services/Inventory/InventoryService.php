<?php

namespace App\Services\Inventory;

use App\Enums\IngredientUnit;
use App\Enums\InventoryTransactionType;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Repositories\Inventory\InventoryRepositoryInterface;
use App\Transfers\Inventory\InventoryTransactionTransferInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService implements InventoryServiceInterface
{
    public function __construct(
        protected InventoryRepositoryInterface $inventory,
        protected InventoryRefillRequestServiceInterface $refillRequests,
    ) {}

    public function recordTransaction(InventoryTransactionTransferInterface $data): InventoryTransaction
    {
        return DB::transaction(function () use ($data): InventoryTransaction {
            $ingredient = $this->inventory->lockIngredient((int) $data->getIngredientId());
            $transactionType = InventoryTransactionType::from((string) $data->getTransactionType());
            $measurementUnit = IngredientUnit::from((string) $data->getMeasurementUnit());
            $baseUnit = $ingredient->base_measurement_unit;

            if (! $baseUnit instanceof IngredientUnit || ! $measurementUnit->supportsBaseUnit($baseUnit)) {
                throw ValidationException::withMessages([
                    'measurement_unit' => 'Selected unit is not compatible with this ingredient.',
                ]);
            }

            $quantity = $this->normalizeDecimal((string) $data->getQuantity(), 3);
            $baseQuantity = $measurementUnit->normalize($quantity, 3);
            $stockBefore = $this->normalizeDecimal((string) $ingredient->current_stock, 3);
            $stockAfter = $this->calculateStockAfter($transactionType, $stockBefore, $baseQuantity);

            if (bccomp($stockAfter, '0', 3) === -1) {
                throw ValidationException::withMessages([
                    'quantity' => 'This transaction would reduce stock below zero.',
                ]);
            }

            $transaction = $this->inventory->createTransaction([
                'ingredient_id' => $ingredient->id,
                'transaction_type' => $transactionType->value,
                'quantity' => $quantity,
                'base_quantity' => $baseQuantity,
                'measurement_unit' => $measurementUnit->value,
                'base_measurement_unit' => $baseUnit->value,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference_type' => $data->getReferenceType(),
                'reference_id' => $data->getReferenceId(),
                'notes' => $data->getNotes(),
                'created_by' => $data->getCreatedBy(),
            ]);

            $this->inventory->updateIngredientStock($ingredient, $stockAfter);
            $this->refillRequests->completeFromInventoryTransaction($transaction);

            return $transaction->fresh(['ingredient.brand', 'ingredient.category', 'createdBy']);
        });
    }

    public function backfillOpeningBalances(string $referenceType = 'seeder_opening_balance'): Collection
    {
        $createdTransactions = new Collection;

        DB::transaction(function () use ($referenceType, $createdTransactions): void {
            $this->inventory->ingredientsWithoutTransactionsWithStock()->each(function (Ingredient $ingredient) use ($referenceType, $createdTransactions): void {
                $createdTransactions->push($this->inventory->createTransaction([
                    'ingredient_id' => $ingredient->id,
                    'transaction_type' => InventoryTransactionType::OpeningBalance->value,
                    'quantity' => $this->normalizeDecimal((string) $ingredient->current_stock, 3),
                    'base_quantity' => $this->normalizeDecimal((string) $ingredient->current_stock, 3),
                    'measurement_unit' => $ingredient->base_measurement_unit?->value,
                    'base_measurement_unit' => $ingredient->base_measurement_unit?->value,
                    'stock_before' => '0.000',
                    'stock_after' => $this->normalizeDecimal((string) $ingredient->current_stock, 3),
                    'reference_type' => $referenceType,
                    'reference_id' => $ingredient->id,
                    'notes' => 'Backfilled opening stock after ingredient seeding.',
                    'created_by' => null,
                ]));
            });
        });

        return $createdTransactions;
    }

    public function compatibleMeasurementUnitOptions(Ingredient $ingredient): array
    {
        return IngredientUnit::optionsForBaseUnit($ingredient->base_measurement_unit ?? $ingredient->measurement_unit);
    }

    protected function calculateStockAfter(InventoryTransactionType $transactionType, string $stockBefore, string $baseQuantity): string
    {
        if ($transactionType->isAbsoluteAdjustment()) {
            return $baseQuantity;
        }

        if ($transactionType->isIncrease()) {
            return bcadd($stockBefore, $baseQuantity, 3);
        }

        if ($transactionType->isDecrease()) {
            return bcsub($stockBefore, $baseQuantity, 3);
        }

        throw ValidationException::withMessages([
            'transaction_type' => 'Unsupported inventory transaction type.',
        ]);
    }

    protected function normalizeDecimal(string $value, int $scale): string
    {
        return bcdiv($value, '1', $scale);
    }
}
