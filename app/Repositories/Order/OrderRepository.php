<?php

namespace App\Repositories\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Repositories\AbstractRepository;
use App\Transfers\Order\OrderFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderRepository extends AbstractRepository implements OrderRepositoryInterface
{
    public function __construct(
        protected Order $model,
        protected ProductVariant $variantModel,
        protected User $userModel,
    ) {}

    public function paginateForAdministrator(OrderFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->orderByRaw($this->statusOrderExpression([
                OrderStatus::PendingPayment,
                OrderStatus::PaymentConfirmed,
                OrderStatus::Accepted,
                OrderStatus::Preparing,
                OrderStatus::ReadyForPickup,
                OrderStatus::Completed,
                OrderStatus::Cancelled,
                OrderStatus::Rejected,
            ]))
            ->orderByDesc('orders.placed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForBarista(OrderFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
            ->orderByRaw($this->statusOrderExpression([
                OrderStatus::PaymentConfirmed,
                OrderStatus::Accepted,
                OrderStatus::Preparing,
                OrderStatus::ReadyForPickup,
                OrderStatus::Completed,
                OrderStatus::PendingPayment,
            ]))
            ->orderBy('orders.placed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForCustomer(User $customer, int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('customer_id', $customer->getKey())
            ->with(['items.addOns', 'statusHistory'])
            ->orderByDesc('placed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function customerOptions(): array
    {
        return $this->userModel->newQuery()
            ->where('role', UserRole::Customer->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function baristaOptions(): array
    {
        return $this->userModel->newQuery()
            ->where('role', UserRole::Barista->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function variantOptions(): array
    {
        return $this->variantModel->newQuery()
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true)->where('is_available', true))
            ->with(['product.category'])
            ->get()
            ->mapWithKeys(function (ProductVariant $variant): array {
                return [
                    $variant->getKey() => sprintf(
                        '%s - %s%s',
                        $variant->product?->name ?? 'Product',
                        $variant->name,
                        $variant->product?->category?->name ? sprintf(' (%s)', $variant->product->category->name) : '',
                    ),
                ];
            })
            ->all();
    }

    public function findOrderableVariant(int $variantId): ?ProductVariant
    {
        return $this->variantModel->newQuery()
            ->with(['product.category', 'recipe.lines.ingredient'])
            ->find($variantId);
    }

    public function findByCheckoutToken(string $checkoutToken): ?Order
    {
        return $this->model->newQuery()
            ->with([
                'customer',
                'items.addOns',
                'statusHistory',
            ])
            ->where('checkout_token', $checkoutToken)
            ->first();
    }

    public function findActiveCustomer(int $customerId): ?User
    {
        return $this->userModel->newQuery()
            ->where('role', UserRole::Customer->value)
            ->where('is_active', true)
            ->find($customerId);
    }

    public function create(array $attributes): Order
    {
        /** @var Order $order */
        $order = $this->persist($this->model->newInstance(), $attributes);

        return $order;
    }

    public function createItems(Order $order, array $items): void
    {
        foreach ($items as $attributes) {
            $addOns = $attributes['add_ons'] ?? [];
            unset($attributes['add_ons']);

            $orderItem = $order->items()->create($attributes);

            foreach ($addOns as $addOn) {
                $orderItem->addOns()->create([
                    'add_on_id' => $addOn['add_on_id'] ?? null,
                    'name' => $addOn['name'],
                    'quantity' => $addOn['quantity'],
                    'unit_price' => $addOn['unit_price'],
                    'total_price' => $addOn['line_total'] ?? $addOn['total_price'] ?? bcmul((string) $addOn['unit_price'], (string) $addOn['quantity'], 2),
                ]);
            }
        }
    }

    public function createStatusHistory(Order $order, array $attributes): void
    {
        $order->statusHistory()->create($attributes);
    }

    public function update(Order $order, array $attributes): Order
    {
        /** @var Order $order */
        $order = $this->persist($order, $attributes);

        return $order;
    }

    public function nextDailySequenceForDate(Carbon $date): int
    {
        $lastSequence = DB::table('orders')
            ->whereDate('order_date', $date->toDateString())
            ->lockForUpdate()
            ->max('daily_sequence');

        return ((int) $lastSequence) + 1;
    }

    public function statusCounts(): array
    {
        $counts = $this->model->newQuery()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    protected function filteredQuery(OrderFilterTransferInterface $filters)
    {
        return $this->model->newQuery()
            ->select('orders.*')
            ->with([
                'customer',
                'assignedBarista',
                'items.recipe.lines.ingredient.brand',
                'statusHistory.changedBy',
            ])
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('orders.order_number', 'like', "%{$search}%")
                        ->orWhere('orders.customer_notes', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                            ->where('product_name', 'like', "%{$search}%")
                            ->orWhere('variant_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters->getStatus(), fn ($query) => $query->where('orders.status', $filters->getStatus()))
            ->when($filters->getCustomerId(), fn ($query) => $query->where('orders.customer_id', $filters->getCustomerId()))
            ->when($filters->getAssignedBaristaId(), fn ($query) => $query->where('orders.assigned_barista_id', $filters->getAssignedBaristaId()));
    }

    protected function statusOrderExpression(array $statuses): string
    {
        $cases = collect($statuses)
            ->values()
            ->map(fn (OrderStatus $status, int $index): string => sprintf(
                "WHEN '%s' THEN %d",
                $status->value,
                $index + 1,
            ))
            ->implode(' ');

        return "CASE orders.status {$cases} ELSE 999 END";
    }
}
