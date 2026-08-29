<?php

namespace App\Parsers\Order;

use App\Transfers\Order\OrderFilterTransferInterface;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransferInterface;

interface OrderParserInterface
{
    public function getTransferFromArrayData(array $orderData): OrderTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): OrderFilterTransferInterface;

    public function getStatusTransitionTransferFromArrayData(array $transitionData): OrderStatusTransitionTransferInterface;
}
