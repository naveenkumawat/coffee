<?php

namespace App\Parsers\Inventory;

use App\Transfers\Inventory\InventoryRefillRequestFilterTransferInterface;
use App\Transfers\Inventory\InventoryRefillRequestTransferInterface;

interface InventoryRefillRequestParserInterface
{
    public function getTransferFromArrayData(array $requestData): InventoryRefillRequestTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): InventoryRefillRequestFilterTransferInterface;
}
