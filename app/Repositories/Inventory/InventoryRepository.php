<?php

namespace App\Repositories\Inventory;

use App\Enums\InventoryStockStatus;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Repositories\AbstractRepository;
use App\Transfers\Inventory\InventoryHistoryFilterTransferInterface;
use App\Transfers\Inventory\InventoryOverviewFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class InventoryRepository extends AbstractRepository implements InventoryRepositoryInterface
{
    public function __construct(
        protected InventoryTransaction $model,
        protected Ingredient $ingredientModel,
    ) {}

    public function paginateOverview(InventoryOverviewFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->ingredientModel->newQuery()
            ->with(['brand', 'category', 'latestInventoryTransaction.createdBy'])
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters->getIngredientCategoryId(), fn ($query) => $query->where('ingredient_category_id', $filters->getIngredientCategoryId()))
            ->when($filters->getIngredientBrandId(), fn ($query) => $query->where('ingredient_brand_id', $filters->getIngredientBrandId()))
            ->when($filters->getMeasurementUnit(), fn ($query) => $query->where('measurement_unit', $filters->getMeasurementUnit()))
            ->when($filters->getStockStatus(), function ($query) use ($filters): void {
                match ($filters->getStockStatus()) {
                    InventoryStockStatus::OutOfStock->value => $query->where('current_stock', '<=', 0),
                    InventoryStockStatus::LowStock->value => $query
                        ->where('current_stock', '>', 0)
                        ->where(function ($nestedQuery): void {
                            $nestedQuery
                                ->where(function ($thresholdQuery): void {
                                    $thresholdQuery
                                        ->where('reorder_level', '>', 0)
                                        ->whereColumn('current_stock', '<=', 'reorder_level');
                                })
                                ->orWhere(function ($thresholdQuery): void {
                                    $thresholdQuery
                                        ->where('reorder_level', '<=', 0)
                                        ->where('minimum_stock', '>', 0)
                                        ->whereColumn('current_stock', '<=', 'minimum_stock');
                                });
                        }),
                    InventoryStockStatus::InStock->value => $query->where(function ($nestedQuery): void {
                        $nestedQuery
                            ->where(function ($thresholdQuery): void {
                                $thresholdQuery
                                    ->where('reorder_level', '>', 0)
                                    ->whereColumn('current_stock', '>', 'reorder_level');
                            })
                            ->orWhere(function ($thresholdQuery): void {
                                $thresholdQuery
                                    ->where('reorder_level', '<=', 0)
                                    ->where(function ($minimumQuery): void {
                                        $minimumQuery
                                            ->where('minimum_stock', '<=', 0)
                                            ->where('current_stock', '>', 0)
                                            ->orWhereColumn('current_stock', '>', 'minimum_stock');
                                    });
                            });
                    }),
                    default => null,
                };
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateHistory(InventoryHistoryFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with(['ingredient.brand', 'ingredient.category', 'createdBy'])
            ->when($filters->getIngredientId(), fn ($query) => $query->where('ingredient_id', $filters->getIngredientId()))
            ->when($filters->getIngredientCategoryId(), fn ($query) => $query->whereHas('ingredient', fn ($ingredientQuery) => $ingredientQuery->where('ingredient_category_id', $filters->getIngredientCategoryId())))
            ->when($filters->getIngredientBrandId(), fn ($query) => $query->whereHas('ingredient', fn ($ingredientQuery) => $ingredientQuery->where('ingredient_brand_id', $filters->getIngredientBrandId())))
            ->when($filters->getTransactionType(), fn ($query) => $query->where('transaction_type', $filters->getTransactionType()))
            ->when($filters->getCreatedBy(), fn ($query) => $query->where('created_by', $filters->getCreatedBy()))
            ->when($filters->getDateFrom(), fn ($query) => $query->whereDate('created_at', '>=', $filters->getDateFrom()))
            ->when($filters->getDateTo(), fn ($query) => $query->whereDate('created_at', '<=', $filters->getDateTo()))
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createTransaction(array $attributes): InventoryTransaction
    {
        /** @var InventoryTransaction $transaction */
        $transaction = $this->persist($this->model->newInstance(), $attributes);

        return $transaction;
    }

    public function findIngredient(int $ingredientId): Ingredient
    {
        return $this->ingredientModel->newQuery()
            ->with(['brand', 'category'])
            ->findOrFail($ingredientId);
    }

    public function lockIngredient(int $ingredientId): Ingredient
    {
        return $this->ingredientModel->newQuery()
            ->with(['brand', 'category'])
            ->lockForUpdate()
            ->findOrFail($ingredientId);
    }

    public function updateIngredientStock(Ingredient $ingredient, string $currentStock): Ingredient
    {
        /** @var Ingredient $updatedIngredient */
        $updatedIngredient = $this->persist($ingredient, [
            'current_stock' => $currentStock,
        ]);

        return $updatedIngredient;
    }

    public function ingredientsWithoutTransactionsWithStock(): Collection
    {
        return $this->ingredientModel->newQuery()
            ->where('current_stock', '>', 0)
            ->whereDoesntHave('inventoryTransactions')
            ->get();
    }

    public function ingredientOptions(bool $activeOnly = false): array
    {
        return $this->ingredientModel->newQuery()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function transactionUserOptions(): array
    {
        return User::query()
            ->whereIn('id', function ($query): void {
                $query->select('created_by')
                    ->from('inventory_transactions')
                    ->whereNotNull('created_by');
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
