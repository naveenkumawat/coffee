<?php

namespace App\Repositories\Order;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Transfers\Order\OrderFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

interface OrderRepositoryInterface
{
    public function paginateForAdministrator(OrderFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator;

    public function paginateForBarista(OrderFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator;

    public function paginateForCustomer(User $customer, int $perPage = 10): LengthAwarePaginator;

    public function customerOptions(): array;

    public function baristaOptions(): array;

    public function variantOptions(): array;

    public function findOrderableVariant(int $variantId): ?ProductVariant;

    public function findActiveCustomer(int $customerId): ?User;

    public function create(array $attributes): Order;

    public function createItems(Order $order, array $items): void;

    public function createStatusHistory(Order $order, array $attributes): void;

    public function update(Order $order, array $attributes): Order;

    public function nextDailySequenceForDate(Carbon $date): int;

    public function statusCounts(): array;
}
