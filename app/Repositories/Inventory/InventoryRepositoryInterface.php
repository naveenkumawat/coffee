<?php

namespace App\Repositories\Inventory;

use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Transfers\Inventory\InventoryHistoryFilterTransferInterface;
use App\Transfers\Inventory\InventoryOverviewFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InventoryRepositoryInterface
{
    public function paginateOverview(InventoryOverviewFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function paginateHistory(InventoryHistoryFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator;

    public function createTransaction(array $attributes): InventoryTransaction;

    public function findIngredient(int $ingredientId): Ingredient;

    public function lockIngredient(int $ingredientId): Ingredient;

    public function updateIngredientStock(Ingredient $ingredient, string $currentStock): Ingredient;

    public function ingredientsWithoutTransactionsWithStock(): Collection;

    public function ingredientOptions(bool $activeOnly = false): array;

    public function transactionUserOptions(): array;
}
