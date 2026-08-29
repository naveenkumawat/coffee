<?php

namespace App\Parsers\Inventory;

use App\Transfers\Inventory\InventoryHistoryFilterTransferInterface;
use App\Transfers\Inventory\InventoryOverviewFilterTransferInterface;
use App\Transfers\Inventory\InventoryTransactionTransferInterface;

interface InventoryParserInterface
{
    public function getTransactionTransferFromArrayData(array $transactionData): InventoryTransactionTransferInterface;

    public function getOverviewFilterTransferFromArrayData(array $filterData): InventoryOverviewFilterTransferInterface;

    public function getHistoryFilterTransferFromArrayData(array $filterData): InventoryHistoryFilterTransferInterface;
}
