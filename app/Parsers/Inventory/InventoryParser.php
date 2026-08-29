<?php

namespace App\Parsers\Inventory;

use App\Parsers\AbstractParser;
use App\Transfers\Inventory\InventoryHistoryFilterTransferInterface;
use App\Transfers\Inventory\InventoryOverviewFilterTransferInterface;
use App\Transfers\Inventory\InventoryTransactionTransferInterface;

class InventoryParser extends AbstractParser implements InventoryParserInterface
{
    public function getTransactionTransferFromArrayData(array $transactionData): InventoryTransactionTransferInterface
    {
        $transfer = $this->make(InventoryTransactionTransferInterface::class);
        $transfer->setIngredientId((int) $transactionData['ingredient_id']);
        $transfer->setTransactionType((string) $transactionData['transaction_type']);
        $transfer->setQuantity((string) $transactionData['quantity']);
        $transfer->setMeasurementUnit((string) $transactionData['measurement_unit']);
        $transfer->setReferenceType(filled($transactionData['reference_type'] ?? null) ? trim((string) $transactionData['reference_type']) : null);
        $transfer->setReferenceId(filled($transactionData['reference_id'] ?? null) ? (int) $transactionData['reference_id'] : null);
        $transfer->setNotes(filled($transactionData['notes'] ?? null) ? trim((string) $transactionData['notes']) : null);
        $transfer->setCreatedBy(filled($transactionData['created_by'] ?? null) ? (int) $transactionData['created_by'] : null);

        return $transfer;
    }

    public function getOverviewFilterTransferFromArrayData(array $filterData): InventoryOverviewFilterTransferInterface
    {
        $transfer = $this->make(InventoryOverviewFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setIngredientCategoryId(filled($filterData['ingredient_category_id'] ?? null) ? (int) $filterData['ingredient_category_id'] : null);
        $transfer->setIngredientBrandId(filled($filterData['ingredient_brand_id'] ?? null) ? (int) $filterData['ingredient_brand_id'] : null);
        $transfer->setMeasurementUnit(filled($filterData['measurement_unit'] ?? null) ? (string) $filterData['measurement_unit'] : null);
        $transfer->setStockStatus(filled($filterData['stock_status'] ?? null) ? (string) $filterData['stock_status'] : null);

        return $transfer;
    }

    public function getHistoryFilterTransferFromArrayData(array $filterData): InventoryHistoryFilterTransferInterface
    {
        $transfer = $this->make(InventoryHistoryFilterTransferInterface::class);
        $transfer->setIngredientId(filled($filterData['ingredient_id'] ?? null) ? (int) $filterData['ingredient_id'] : null);
        $transfer->setIngredientCategoryId(filled($filterData['ingredient_category_id'] ?? null) ? (int) $filterData['ingredient_category_id'] : null);
        $transfer->setIngredientBrandId(filled($filterData['ingredient_brand_id'] ?? null) ? (int) $filterData['ingredient_brand_id'] : null);
        $transfer->setTransactionType(filled($filterData['transaction_type'] ?? null) ? (string) $filterData['transaction_type'] : null);
        $transfer->setCreatedBy(filled($filterData['created_by'] ?? null) ? (int) $filterData['created_by'] : null);
        $transfer->setDateFrom(filled($filterData['date_from'] ?? null) ? (string) $filterData['date_from'] : null);
        $transfer->setDateTo(filled($filterData['date_to'] ?? null) ? (string) $filterData['date_to'] : null);

        return $transfer;
    }
}
