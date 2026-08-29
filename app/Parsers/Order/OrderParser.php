<?php

namespace App\Parsers\Order;

use App\Transfers\Order\OrderFilterTransferInterface;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransferInterface;

class OrderParser implements OrderParserInterface
{
    public function __construct(
        protected OrderTransferInterface $transfer,
        protected OrderFilterTransferInterface $filterTransfer,
        protected OrderStatusTransitionTransferInterface $statusTransitionTransfer,
    ) {}

    public function getTransferFromArrayData(array $orderData): OrderTransferInterface
    {
        $transfer = clone $this->transfer;
        $transfer->setCustomerId(filled($orderData['customer_id'] ?? null) ? (int) $orderData['customer_id'] : null);
        $transfer->setCustomerNotes(filled($orderData['customer_notes'] ?? null) ? trim((string) $orderData['customer_notes']) : null);
        $transfer->setItems(
            collect($orderData['items'] ?? [])
                ->filter(fn (array $item): bool => filled($item['product_variant_id'] ?? null) || filled($item['quantity'] ?? null))
                ->map(fn (array $item): array => [
                    'product_variant_id' => filled($item['product_variant_id'] ?? null) ? (int) $item['product_variant_id'] : null,
                    'quantity' => filled($item['quantity'] ?? null) ? (int) $item['quantity'] : null,
                ])
                ->values()
                ->all(),
        );

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): OrderFilterTransferInterface
    {
        $transfer = clone $this->filterTransfer;
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);
        $transfer->setCustomerId(filled($filterData['customer_id'] ?? null) ? (int) $filterData['customer_id'] : null);
        $transfer->setAssignedBaristaId(filled($filterData['assigned_barista_id'] ?? null) ? (int) $filterData['assigned_barista_id'] : null);

        return $transfer;
    }

    public function getStatusTransitionTransferFromArrayData(array $transitionData): OrderStatusTransitionTransferInterface
    {
        $transfer = clone $this->statusTransitionTransfer;
        $transfer->setStatus(filled($transitionData['status'] ?? null) ? (string) $transitionData['status'] : null);
        $transfer->setNotes(filled($transitionData['notes'] ?? null) ? trim((string) $transitionData['notes']) : null);

        return $transfer;
    }
}
