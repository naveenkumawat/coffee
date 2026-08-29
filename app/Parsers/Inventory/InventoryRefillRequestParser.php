<?php

namespace App\Parsers\Inventory;

use App\Parsers\AbstractParser;
use App\Transfers\Inventory\InventoryRefillRequestFilterTransferInterface;
use App\Transfers\Inventory\InventoryRefillRequestTransferInterface;

class InventoryRefillRequestParser extends AbstractParser implements InventoryRefillRequestParserInterface
{
    public function getTransferFromArrayData(array $requestData): InventoryRefillRequestTransferInterface
    {
        $transfer = $this->make(InventoryRefillRequestTransferInterface::class);
        $transfer->setIngredientId(filled($requestData['ingredient_id'] ?? null) ? (int) $requestData['ingredient_id'] : null);
        $transfer->setQuantity(filled($requestData['quantity'] ?? null) ? (string) $requestData['quantity'] : null);
        $transfer->setMeasurementUnit(filled($requestData['measurement_unit'] ?? null) ? (string) $requestData['measurement_unit'] : null);
        $transfer->setNotes(filled($requestData['notes'] ?? null) ? trim((string) $requestData['notes']) : null);
        $transfer->setStatus(filled($requestData['status'] ?? null) ? (string) $requestData['status'] : null);
        $transfer->setReviewNotes(filled($requestData['review_notes'] ?? null) ? trim((string) $requestData['review_notes']) : null);

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): InventoryRefillRequestFilterTransferInterface
    {
        $transfer = $this->make(InventoryRefillRequestFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setIngredientId(filled($filterData['ingredient_id'] ?? null) ? (int) $filterData['ingredient_id'] : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);
        $transfer->setRequestedBy(filled($filterData['requested_by'] ?? null) ? (int) $filterData['requested_by'] : null);

        return $transfer;
    }
}
