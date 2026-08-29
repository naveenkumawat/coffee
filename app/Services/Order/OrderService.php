<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransferInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService implements OrderServiceInterface
{
    public function __construct(
        protected OrderRepositoryInterface $orders,
    ) {}

    public function store(User $actor, OrderTransferInterface $data): Order
    {
        return DB::transaction(function () use ($actor, $data): Order {
            $customerId = $data->getCustomerId();
            $customer = null;

            if ($customerId !== null) {
                $customer = $this->orders->findActiveCustomer($customerId);

                if (! $customer) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'Only active customer accounts can be linked to an order.',
                    ]);
                }
            }

            $preparedItems = $this->prepareItems($data->getItems());

            if ($preparedItems === []) {
                throw ValidationException::withMessages([
                    'items' => 'At least one order item is required.',
                ]);
            }

            $subtotal = $this->sumLineSubtotals($preparedItems);
            $placedAt = now();
            $dailySequence = $this->orders->nextDailySequenceForDate(Carbon::instance($placedAt));

            $order = $this->orders->create([
                'order_number' => $this->formatOrderNumber(Carbon::instance($placedAt), $dailySequence),
                'order_date' => $placedAt->toDateString(),
                'daily_sequence' => $dailySequence,
                'customer_id' => $customerId,
                'customer_name' => $data->getCustomerName() ?: $customer?->name,
                'customer_email' => $data->getCustomerEmail() ?: $customer?->email,
                'customer_phone' => $data->getCustomerPhone() ?: $customer?->phone,
                'pickup_name' => $data->getPickupName(),
                'pickup_phone' => $data->getPickupPhone(),
                'assigned_barista_id' => null,
                'checkout_token' => $data->getCheckoutToken(),
                'status' => OrderStatus::PendingPayment->value,
                'subtotal' => $subtotal,
                'discount_total' => '0.00',
                'total_amount' => $subtotal,
                'customer_notes' => $data->getCustomerNotes(),
                'pickup_notes' => $data->getPickupNotes(),
                'placed_at' => $placedAt,
            ]);

            $this->orders->createItems($order, $preparedItems);
            $this->orders->createStatusHistory($order, [
                'from_status' => null,
                'to_status' => OrderStatus::PendingPayment->value,
                'changed_by' => $actor->getKey(),
                'notes' => 'Order created.',
            ]);

            return $order->fresh([
                'customer',
                'assignedBarista',
                'items.recipe.lines.ingredient.brand',
                'statusHistory.changedBy',
            ]);
        });
    }

    public function transition(Order $order, User $actor, OrderStatusTransitionTransferInterface $data): Order
    {
        return DB::transaction(function () use ($order, $actor, $data): Order {
            $currentStatus = $order->status;
            $nextStatus = OrderStatus::from((string) $data->getStatus());

            if (! $currentStatus instanceof OrderStatus) {
                throw ValidationException::withMessages([
                    'status' => 'The current order status is invalid.',
                ]);
            }

            if ($currentStatus === $nextStatus) {
                throw ValidationException::withMessages([
                    'status' => 'The order is already in the selected status.',
                ]);
            }

            $allowedStatuses = $this->availableTransitions($order, $actor);

            if (! array_key_exists($nextStatus->value, $allowedStatuses)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected status transition is not allowed.',
                ]);
            }

            $attributes = ['status' => $nextStatus->value];

            match ($nextStatus) {
                OrderStatus::PaymentConfirmed => $attributes['payment_confirmed_at'] = $order->payment_confirmed_at ?: now(),
                OrderStatus::Accepted => $attributes['accepted_at'] = $order->accepted_at ?: now(),
                OrderStatus::Preparing => $attributes['preparing_at'] = $order->preparing_at ?: now(),
                OrderStatus::ReadyForPickup => $attributes['ready_for_pickup_at'] = $order->ready_for_pickup_at ?: now(),
                OrderStatus::Completed => $attributes['completed_at'] = $order->completed_at ?: now(),
                OrderStatus::Cancelled => $attributes['cancelled_at'] = $order->cancelled_at ?: now(),
                OrderStatus::Rejected => $attributes['rejected_at'] = $order->rejected_at ?: now(),
                default => null,
            };

            if (
                $actor->hasRole(UserRole::Barista)
                && in_array($nextStatus, [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::Completed], true)
                && ! $order->assigned_barista_id
            ) {
                $attributes['assigned_barista_id'] = $actor->getKey();
            }

            $order = $this->orders->update($order, $attributes);
            $this->orders->createStatusHistory($order, [
                'from_status' => $currentStatus->value,
                'to_status' => $nextStatus->value,
                'changed_by' => $actor->getKey(),
                'notes' => $data->getNotes(),
            ]);

            return $order->fresh([
                'customer',
                'assignedBarista',
                'items.recipe.lines.ingredient.brand',
                'statusHistory.changedBy',
            ]);
        });
    }

    public function availableTransitions(Order $order, User $actor): array
    {
        $currentStatus = $order->status;

        if (! $currentStatus instanceof OrderStatus || $currentStatus->isTerminal()) {
            return [];
        }

        $workflow = match ($currentStatus) {
            OrderStatus::PendingPayment => [OrderStatus::PaymentConfirmed, OrderStatus::Cancelled, OrderStatus::Rejected],
            OrderStatus::PaymentConfirmed => [OrderStatus::Accepted, OrderStatus::Cancelled, OrderStatus::Rejected],
            OrderStatus::Accepted => [OrderStatus::Preparing, OrderStatus::Cancelled],
            OrderStatus::Preparing => [OrderStatus::ReadyForPickup, OrderStatus::Cancelled],
            OrderStatus::ReadyForPickup => [OrderStatus::Completed],
            OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Rejected => [],
        };

        if ($actor->canManageOrders()) {
            return collect($workflow)->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->label()])->all();
        }

        if (! $actor->hasRole(UserRole::Barista)) {
            return [];
        }

        $baristaAllowed = array_filter(
            $workflow,
            fn (OrderStatus $status): bool => in_array(
                $status,
                [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::Completed],
                true,
            ),
        );

        return collect($baristaAllowed)->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->label()])->all();
    }

    protected function prepareItems(array $items): array
    {
        $prepared = [];
        $variantIds = [];

        foreach ($items as $index => $item) {
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($variantId <= 0 || $quantity <= 0) {
                continue;
            }

            if (in_array($variantId, $variantIds, true)) {
                throw ValidationException::withMessages([
                    "items.$index.product_variant_id" => 'Duplicate product variants are not allowed in the same order.',
                ]);
            }

            $variant = $this->validateVariant($variantId, $index);
            $variantIds[] = $variantId;

            $unitPrice = $this->normalizeMoney((string) $variant->price);
            $lineSubtotal = bcmul($unitPrice, (string) $quantity, 2);

            $prepared[] = [
                'product_id' => $variant->product?->getKey(),
                'product_variant_id' => $variant->getKey(),
                'recipe_id' => $variant->recipe?->getKey(),
                'product_name' => $variant->product?->name ?? 'Product',
                'variant_name' => $variant->name,
                'customer_ingredient_summary' => $variant->product?->customer_ingredient_summary,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_subtotal' => $lineSubtotal,
            ];
        }

        return $prepared;
    }

    protected function validateVariant(int $variantId, int $index): ProductVariant
    {
        $variant = $this->orders->findOrderableVariant($variantId);

        if (
            ! $variant
            || ! $variant->is_active
            || ! $variant->is_available
            || ! $variant->product
            || ! $variant->product->is_active
            || ! $variant->product->is_available
        ) {
            throw ValidationException::withMessages([
                "items.$index.product_variant_id" => 'Only active and available product variants can be ordered.',
            ]);
        }

        return $variant;
    }

    protected function sumLineSubtotals(array $items): string
    {
        return collect($items)->reduce(
            fn (string $carry, array $item): string => bcadd($carry, (string) $item['line_subtotal'], 2),
            '0.00',
        );
    }

    protected function normalizeMoney(string $value): string
    {
        return bcdiv($value, '1', 2);
    }

    protected function formatOrderNumber(Carbon $placedAt, int $dailySequence): string
    {
        return sprintf('CC-%s-%s', $placedAt->format('dmy'), str_pad((string) $dailySequence, 4, '0', STR_PAD_LEFT));
    }
}
